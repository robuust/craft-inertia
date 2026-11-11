<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Http\Controllers;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\CurrentElement;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Inertia\Ssr\DisablesSsr;
use Inertia\Ssr\Gateway;
use LogicException;
use Robuust\CraftInertia\Content\EntryProjection;
use Robuust\CraftInertia\Http\HandleEntryRequests;
use Symfony\Component\HttpFoundation\Response;

/** Renders the authorized native element through an isolated public response. */
final class EntryController
{
    /**
     * @param Request $request Native site request after site/token resolution.
     * @param Entry $entry Craft's matched live or authorized preview element.
     * @param string $component Explicit component selected by the section template.
     * @return Response Official Inertia HTML or page JSON with public response policies.
     */
    public function __invoke(Request $request, #[CurrentElement] Entry $entry, string $component): Response
    {
        $original = app(ResponseFactory::class);
        $public = clone $original;
        $public->flushShared();
        $gateway = app(Gateway::class);
        $middleware = app(HandleEntryRequests::class);
        $guest = $middleware->isPublicGuest($request, $entry);

        // Isolate this response without replacing the host's SSR callbacks or shares.
        if (!$guest || !config('inertia.ssr.enabled')) {
            $private = clone $gateway;
            if (!$private instanceof DisablesSsr) {
                throw new LogicException('Entry templates require an SSR gateway supporting Inertia\\Ssr\\DisablesSsr.');
            }
            $private->disable(true);
            app()->instance(Gateway::class, $private);
        }
        Inertia::swap($public);

        try {
            return $middleware->handle($request, function () use ($request, $entry, $component, $guest, $middleware): Response {
                $site = Sites::getCurrentSite();
                return Inertia::render($component, [
                    'entry' => app(EntryProjection::class)->entry($entry, config("craft-inertia.pages.$component.fields", [])),
                    'site' => [
                        'id' => $site->id, 'uid' => $site->uid, 'handle' => $site->handle,
                        'name' => $site->name, 'language' => $site->language, 'baseUrl' => $site->getBaseUrl(),
                    ],
                    'preview' => $entry->previewing,
                    'seo' => [
                        'title' => $entry->title, 'canonical' => $entry->getUrl() === null ? null : Url::removeAllParams($entry->getUrl()),
                        'robots' => $guest ? $middleware->robots($request) : 'noindex, nofollow',
                    ],
                ])->toResponse($request);
            });
        } finally {
            Inertia::swap($original);
            app()->instance(Gateway::class, $gateway);
        }
    }
}
