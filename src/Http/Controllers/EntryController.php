<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Http\Controllers;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\CurrentElement;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Url;
use Illuminate\Http\Request;
use Robuust\CraftInertia\Content\EntryProjection;
use Robuust\CraftInertia\Http\PublicRenderer;
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
        return app(PublicRenderer::class)->render($request, $component, function (string $robots) use ($entry): array {
            $site = Sites::getCurrentSite();

            return [
                'entry' => app(EntryProjection::class)->entry($entry),
                'site' => [
                    'id' => $site->id, 'uid' => $site->uid, 'handle' => $site->handle,
                    'name' => $site->name, 'language' => $site->language, 'baseUrl' => $site->getBaseUrl(),
                ],
                'preview' => $entry->previewing,
                'seo' => [
                    'title' => $entry->title, 'canonical' => $entry->getUrl() === null ? null : Url::removeAllParams($entry->getUrl()),
                    'robots' => $robots,
                ],
            ];
        }, $entry);
    }
}
