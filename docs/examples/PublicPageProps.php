<?php

declare(strict_types=1);

namespace App\PublicFrontend;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\ElementCaches;
use Robuust\CraftInertia\Http\PublicPageContext;

/** Project example: stateless public navigation with cacheable callback registration. */
final class PublicPageProps
{
    /**
     * @param PublicPageContext $context Actual matched entry, current site and request.
     * @return array<string, mixed> Explicitly selected public navigation data.
     */
    public static function navigation(PublicPageContext $context): array
    {
        $items = Entry::find()->section('navigation')->siteId($context->site->id)->all();
        $navigation = [];
        foreach ($items as $item) {
            ElementCaches::collectCacheInfoForElement($item);
            $navigation[] = ['title' => $item->title, 'url' => $item->getUrl()];
        }
        return ['layout' => ['navigation' => $navigation]];
    }
}
