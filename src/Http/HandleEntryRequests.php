<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Http;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\Middleware\ResolveSite;
use CraftCms\Cms\Route\MatchedElement;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Override;

/** Adds native entry privacy eligibility to the existing public protocol middleware. */
final class HandleEntryRequests extends HandleInertiaRequests
{
    /** {@inheritdoc} */
    #[Override]
    public function robots(Request $request, ?Entry $entry = null): string
    {
        $entry ??= MatchedElement::get() ?: null;
        return $entry instanceof Entry && $this->isPublicGuest($request, $entry)
            ? parent::robots($request)
            : 'noindex, nofollow';
    }

    /**
     * @param Request $request Native site/token/session context.
     * @param Entry|null $entry Actual matched entry, without a canonical requery.
     * @return bool Whether this response is eligible for guest SSR and project indexing.
     */
    public function isPublicGuest(Request $request, ?Entry $entry): bool
    {
        if (!$request->isSiteRequest() || $request->isActionRequest()
            || !$request->isMethodSafe() || $request->craftUser() !== null
            || $entry === null || $entry->previewing || $entry->getIsDraft() || $entry->getIsRevision()
            || $entry->getStatus() !== Entry::STATUS_LIVE || $entry->siteId !== Sites::getCurrentSite()->id
            || $request->isPreview() || $request->getHadToken()
            || Context::getHidden(ResolveSite::HAD_SITE_TOKEN_KEY) === true) {
            return false;
        }
        foreach ([Cms::config()->tokenParam, Cms::config()->siteToken, 'x-craft-preview', 'x-craft-live-preview'] as $key) {
            if ($request->query->has($key)) {
                return false;
            }
        }
        if ($request->hasHeader('X-Craft-Token') || $request->hasHeader('X-Craft-Preview-Token')
            || $request->hasHeader('X-Craft-Site-Token')
            || ($request->hasSession() && ($request->session()->hasAny(['errors', 'notice', 'success', 'error', '_old_input'])
                || Inertia::getFlashed($request) !== []))) {
            return false;
        }

        $policy = config('craft-inertia.publicPagePolicy');

        return $policy === null || (is_callable($policy) && $policy($request, $entry) === true);
    }
}
