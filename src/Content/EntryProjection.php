<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Content;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\LinkData;
use CraftCms\Cms\Field\Data\MarkdownData;
use CraftCms\Cms\Field\Data\MultiOptionsFieldData;
use CraftCms\Cms\Field\Data\OptionData;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlSanitizers;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;

/** All native layout fields, without exposing the element's internal attributes. */
final class EntryProjection
{
    /** @return array<string, mixed> Native entry identity and all attached field values. */
    public function entry(Entry $entry): array
    {
        return $this->project($entry);
    }

    /**
     * @param array<string, true> $ancestors Entry identities on this relation path.
     * @return array<string, mixed> Repeated/deep entries retain identity with empty fields.
     */
    private function project(Entry $entry, array $ancestors = [], int $depth = 0): array
    {
        ElementCaches::collectCacheInfoForElement($entry);
        $identity = $entry->id !== null ? "$entry->id:$entry->siteId" : 'new:'.spl_object_id($entry);
        $fields = isset($ancestors[$identity]) || $depth >= 10 ? [] : ($entry->getFieldLayout()?->getCustomFields() ?? []);
        $ancestors[$identity] = true;
        $relations = [];
        foreach ($fields as $field) {
            if ($field instanceof Field\Assets || $field instanceof Field\Entries || $field instanceof Field\Matrix) {
                $relations[] = $field->handle;
            }
        }
        if ($relations !== []) {
            Elements::eagerLoadElements(Entry::class, [$entry], $relations);
        }

        $values = [];
        foreach ($fields as $field) {
            $values[$field->handle] = $this->field($entry, $field, $ancestors, $depth);
        }
        return [
            'id' => $entry->id, 'uid' => $entry->uid, 'canonicalId' => $entry->getCanonicalId(),
            'title' => $entry->title, 'slug' => $entry->slug, 'url' => $entry->getUrl(),
            'postDate' => $entry->postDate?->format(DATE_ATOM),
            'type' => $entry->getType()->handle, 'fields' => $values,
        ];
    }

    /**
     * @param array<string, true> $ancestors Entry identities on this relation path.
     * @return mixed JSON-compatible native field data.
     */
    private function field(Entry $entry, FieldInterface $field, array $ancestors, int $depth): mixed
    {
        $handle = $field->handle;
        if ($field instanceof Field\Assets || $field instanceof Field\Entries || $field instanceof Field\Matrix) {
            $elements = $entry->getEagerLoadedElements($handle) ?? $entry->getFieldValue($handle)->all();
            $values = [];
            foreach ($elements as $element) {
                ElementCaches::collectCacheInfoForElement($element);
                if ($element instanceof Asset) {
                    $values[] = $this->asset($element);
                } elseif ($element instanceof Entry) {
                    $values[] = $this->project($element, $ancestors, $depth + 1);
                } else {
                    throw new InvalidArgumentException("Unsupported related element for entry field $handle.");
                }
            }
            return $values;
        }

        $value = $entry->getFieldValue($handle);
        return $this->publicValue(match (true) {
            $value instanceof MarkdownData => HtmlSanitizers::sanitize($value->getHtml()),
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof MultiOptionsFieldData => array_map($this->option(...), iterator_to_array($value)),
            $value instanceof OptionData => $this->option($value),
            $value instanceof LinkData => $value->toArray(),
            default => $field->serializeValue($value, $entry),
        });
    }

    /** @return array<string, mixed> Native original asset URL, dimensions and editorial text. */
    private function asset(Asset $asset): array
    {
        return [
            'id' => $asset->id, 'uid' => $asset->uid, 'title' => $asset->title,
            'url' => $asset->getUrl(), 'width' => $asset->getWidth(),
            'height' => $asset->getHeight(), 'alt' => $asset->alt, 'srcset' => null,
        ];
    }

    /** @return array{value: ?string, label: ?string} Native choice identity and label. */
    private function option(OptionData $option): array
    {
        return ['value' => $option->value, 'label' => $option->label];
    }

    /** @return mixed Finite JSON values from the field's own serialization contract. */
    private function publicValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 20) {
            throw new InvalidArgumentException('An entry field value exceeds twenty nested JSON levels.');
        }
        if ($value instanceof JsonSerializable) {
            return $this->publicValue($value->jsonSerialize(), $depth + 1);
        }
        if ($value instanceof stdClass) {
            return (object) $this->publicValue((array) $value, $depth + 1);
        }
        if ((!is_null($value) && !is_scalar($value) && !is_array($value)) || (is_float($value) && !is_finite($value))) {
            throw new InvalidArgumentException('An entry field must serialize to finite JSON scalars, arrays or null.');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->publicValue($item, $depth + 1);
            }
        }
        return $value;
    }
}
