<?php

declare(strict_types=1);

namespace App\PublicFrontend;

use CraftCms\Cms\Field\Data\LinkData;
use Robuust\CraftInertia\Content\FieldContext;
use Robuust\CraftInertia\Content\LinkProjection;

/** Project example: selected article relations and a publication-aware CTA. */
final class ArticleSerializers
{
    /**
     * @param FieldContext $context Current native field and owner.
     * @return bool Whether the field is this schema's article cover.
     */
    public static function matchesCover(FieldContext $context): bool
    {
        return $context->entry->getType()->handle === 'article' && $context->field->handle === 'cover';
    }

    /**
     * @param FieldContext $context Article cover's resolved native asset list.
     * @return list<array<string, mixed>> Original assets with selected image variants.
     */
    public static function cover(FieldContext $context): array
    {
        $images = [];
        foreach ($context->value as $asset) {
            $images[] = $context->projectAsset($asset, ['editorialCard']);
        }
        return $images;
    }

    /**
     * @param FieldContext $context Current native field and owner.
     * @return bool Whether the field is this schema's related articles.
     */
    public static function matchesRelated(FieldContext $context): bool
    {
        return $context->entry->getType()->handle === 'article' && $context->field->handle === 'related';
    }

    /**
     * @param FieldContext $context Related field's resolved native entry list.
     * @return list<array<string, mixed>> Related identities with only selected summary fields.
     */
    public static function related(FieldContext $context): array
    {
        $entries = [];
        foreach ($context->value as $entry) {
            $entries[] = $context->projectEntry($entry, ['summary']);
        }
        return $entries;
    }

    /**
     * @param FieldContext $context Current native field and owner.
     * @return bool Whether the field contains the project's native CTA Link.
     */
    public static function matchesCta(FieldContext $context): bool
    {
        return $context->field->handle === 'cta' && $context->value instanceof LinkData;
    }

    /**
     * @param FieldContext $context Matched native Link value.
     * @return array<string, mixed>|null Selected link data or null for an unavailable destination.
     */
    public static function cta(FieldContext $context): ?array
    {
        return app(LinkProjection::class)->live($context->value);
    }
}
