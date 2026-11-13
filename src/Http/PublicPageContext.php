<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Http;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Site\Data\Site;
use Illuminate\Http\Request;

/** Request-local context for project-owned public props. */
final readonly class PublicPageContext
{
    /**
     * @param Entry $entry Actual matched live or authorized preview entry.
     * @param Site $site Current resolved site.
     * @param Request $request Current native request; never serialize this object.
     * @param string $robots Renderer's effective indexing boundary; project metadata may only restrict it further.
     */
    public function __construct(
        public Entry $entry,
        public Site $site,
        public Request $request,
        public string $robots,
    ) {
    }
}
