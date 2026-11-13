<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Content;

use CraftCms\Cms\Field\Data\LinkData;
use CraftCms\Cms\Support\Facades\ElementCaches;

/** Opt-in publication-aware Link projection without permissive duplicate URLs. */
final class LinkProjection
{
    /**
     * @param LinkData $link Native Link value in its current site/preview context.
     * @return array<string, mixed>|null Selected link data, or null for an unavailable destination.
     */
    public function live(LinkData $link): ?array
    {
        $url = $link->getUrl(false);
        $element = $link->getElement();
        if ($element !== null) {
            ElementCaches::collectCacheInfoForElement($element);
        }
        if ($url === '') {
            return null;
        }
        $attributes = $link->getAttributes();
        $attributes['href'] = $url;
        return PublicValue::normalize([
            'url' => $url, 'label' => $link->getLabel(), 'target' => $link->target,
            'urlSuffix' => $link->urlSuffix, 'attributes' => $attributes,
        ], 'live link');
    }
}
