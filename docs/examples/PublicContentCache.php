<?php

namespace App\Http;

use Closure;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\DependencyAwareCache\Facades\DependencyCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/** Project example: cache explicit public arrays, never responses or elements. */
final class PublicContentCache
{
    /**
     * @param array            $inputs  normalized content/query inputs, including the flow name
     * @param Closure(): array $project public data only; no request/session/SEO policy
     *
     * @return array
     */
    public static function remember(Request $request, array $inputs, Closure $project): array
    {
        if (config('site-content.cacheEnabled') !== true
            || $request->route()?->getMetadata('public.contentCache') !== true
            || !PublicRendering::allowsSsr($request)
            || ($request->hasSession() && $request->session()->has('_old_input'))) {
            return $project();
        }

        $site = Sites::getCurrentSite();
        $key = 'site-content:'.hash('sha256', json_encode([
            // Bump when projection code, transforms or deployment inputs change.
            'v1', $site->id, $site->uid, $site->getBaseUrl(),
            config('site-content.articles'), config('site-content.taxonomy'), $inputs,
        ], JSON_THROW_ON_ERROR));
        $cached = DependencyCache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        ElementCaches::startCollectingCacheInfo();
        try {
            // Include off-page and currently invisible entries. Native live queries
            // change at the start of a minute in this pinned Craft version.
            $query = Entry::find()->siteId($site->id)->ignorePlaceholders();
            $pending = (clone $query)->status(Entry::STATUS_PENDING)->orderBy('postDate')->one();
            if ($pending?->postDate !== null) {
                ElementCaches::setCacheExpiryDate(Date::instance($pending->postDate)->startOfMinute());
            }
            $expiring = (clone $query)->expiryDate('> now')->orderBy('expiryDate')->one();
            if ($expiring?->expiryDate !== null) {
                ElementCaches::setCacheExpiryDate(Date::instance($expiring->expiryDate)->startOfMinute());
            }
            $data = $project();
        } finally {
            [$dependency, $duration] = ElementCaches::stopCollectingCacheInfo();
        }

        $duration = min(300, $duration ?? 300);
        if ($duration > 0) {
            DependencyCache::put($key, $data, $duration, $dependency);
        }

        return $data;
    }
}
