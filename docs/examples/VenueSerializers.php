<?php

declare(strict_types=1);

namespace App\PublicFrontend;

use Robuust\CraftInertia\Content\FieldContext;

/** Project example: a custom coordinate field and a separate gallery policy. */
final class VenueSerializers
{
    /**
     * @param FieldContext $context Current native field and owner.
     * @return bool Whether the field is this schema's custom map point.
     */
    public static function matchesCoordinates(FieldContext $context): bool
    {
        return $context->entry->getType()->handle === 'venue' && $context->field->handle === 'mapPoint';
    }

    /**
     * @param FieldContext $context Custom field with a native lat/lng array or null contract.
     * @return array{latitude: float, longitude: float}|null Deliberately selected public coordinate shape.
     */
    public static function coordinates(FieldContext $context): ?array
    {
        $point = $context->defaultValue();
        return $point === null ? null : [
            'latitude' => round($point['lat'], 5),
            'longitude' => round($point['lng'], 5),
        ];
    }

    /**
     * @param FieldContext $context Current native field and owner.
     * @return bool Whether the field is this schema's venue gallery.
     */
    public static function matchesGallery(FieldContext $context): bool
    {
        return $context->entry->getType()->handle === 'venue' && $context->field->handle === 'gallery';
    }

    /**
     * @param FieldContext $context Venue gallery's resolved native asset list.
     * @return list<array<string, mixed>> Original assets with selected gallery variants.
     */
    public static function gallery(FieldContext $context): array
    {
        $images = [];
        foreach ($context->value as $asset) {
            $images[] = $context->projectAsset($asset, ['galleryFit']);
        }
        return $images;
    }
}
