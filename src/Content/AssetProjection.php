<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Content;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Support\Facades\ElementCaches;
use InvalidArgumentException;

/** Adds project-selected native responsive variants without changing original assets. */
final class AssetProjection
{
    /**
     * @param Asset $asset Native asset selected through an authorized project relation/query.
     * @param list<string> $profiles Project profile names to add under variants.
     * @return array<string, mixed> Original asset identity, URL, dimensions and editorial text.
     */
    public function project(Asset $asset, array $profiles = []): array
    {
        ElementCaches::collectCacheInfoForElement($asset);
        $original = [
            'id' => $asset->id, 'uid' => $asset->uid, 'title' => $asset->title,
            'url' => $asset->getUrl(), 'width' => $asset->getWidth(),
            'height' => $asset->getHeight(), 'alt' => $asset->alt, 'srcset' => null,
        ];
        if ($profiles === []) {
            return $original;
        }
        $variants = [];
        foreach ($profiles as $name) {
            $profile = $this->profile($name);
            // A native transform can expose an action URL even for a source without public URLs.
            // Preserve the default public URL boundary; SVGs retain their native original path.
            if ($original['url'] === null || $asset->kind !== 'image' || strtolower($asset->getExtension()) === 'svg') {
                continue;
            }
            $result = $asset->transform($profile['transform']);
            $variants[$name] = [
                'url' => $result->url, 'width' => $result->width, 'height' => $result->height,
                'srcset' => $profile['widths'] === [] ? null : $asset->getSrcset(
                    array_map(static fn (int $width): string => $width.'w', $profile['widths']),
                    $profile['transform'],
                ),
            ];
        }
        return PublicValue::normalize($original + ['variants' => $variants], 'asset');
    }

    /**
     * @param mixed $name Selected profile name.
     * @return array{transform: string|array<string, mixed>, widths: list<int>} Validated native transform definition.
     */
    private function profile(mixed $name): array
    {
        $profiles = config('craft-inertia.imageProfiles', []);
        if (!is_string($name) || $name === '' || !is_array($profiles) || !isset($profiles[$name]) || !is_array($profiles[$name])) {
            throw new InvalidArgumentException('An image profile must name an array in craft-inertia.imageProfiles.');
        }
        $profile = $profiles[$name];
        $transform = $profile['transform'] ?? null;
        $widths = $profile['widths'] ?? [];
        if ((!is_string($transform) && !is_array($transform)) || $transform === ''
            || !is_array($widths) || !array_is_list($widths)) {
            throw new InvalidArgumentException("Image profile $name requires a native transform handle/array and a list of positive integer widths.");
        }
        foreach ($widths as $width) {
            if (!is_int($width) || $width <= 0) {
                throw new InvalidArgumentException("Image profile $name widths must be positive integers.");
            }
        }
        return ['transform' => $transform, 'widths' => $widths];
    }
}
