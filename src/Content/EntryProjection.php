<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Content;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Data\MarkdownData;
use CraftCms\Cms\Field\Data\MultiOptionsFieldData;
use CraftCms\Cms\Field\Data\OptionData;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlSanitizers;
use DateTimeInterface;
use InvalidArgumentException;

/** Explicit public values, selected before any field is resolved. No storage serialization. */
final class EntryProjection
{
    /**
     * @param Entry $entry The native element to project, including preview identity.
     * @param array<array-key, mixed> $fields Explicit native field selection.
     * @param int $depth Current bounded relation depth.
     * @return array<string, mixed> Safe base entry values and selected fields.
     */
    public function entry(Entry $entry, array $fields = [], int $depth = 0): array
    {
        if ($depth > 10) {
            throw new InvalidArgumentException('Entry field selection exceeds ten nested levels.');
        }
        ElementCaches::collectCacheInfoForElement($entry);
        $selection = $this->selection($fields);
        $layout = $entry->getFieldLayout();
        $relations = [];
        foreach ($selection as $handle => $options) {
            $field = $layout?->getFieldByHandle($handle);
            if ($field && in_array($field::class, [Field\Assets::class, Field\Entries::class, Field\Matrix::class], true)) {
                $relations[] = $handle;
            }
        }
        if ($relations !== []) {
            Elements::eagerLoadElements(Entry::class, [$entry], $relations);
        }

        $values = [];
        foreach ($selection as $handle => $options) {
            if ($field = $layout?->getFieldByHandle($handle)) {
                $values[$handle] = $this->field($entry, $field, $options, $depth);
            }
        }
        return [
            'id' => $entry->id, 'uid' => $entry->uid, 'canonicalId' => $entry->getCanonicalId(),
            'title' => $entry->title, 'slug' => $entry->slug, 'url' => $entry->getUrl(),
            'postDate' => $entry->postDate?->format(DATE_ATOM),
            'type' => $entry->getType()->handle, 'fields' => $values,
        ];
    }

    /**
     * @param array<array-key, mixed> $fields Project selection before resolving values.
     * @return array<string, array<string, mixed>> Validated handles and options.
     */
    private function selection(array $fields): array
    {
        $selection = [];
        foreach ($fields as $key => $options) {
            $handle = is_int($key) ? $options : $key;
            $options = is_int($key) ? [] : $options;
            if (!is_string($handle) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $handle) || !is_array($options)
                || array_diff(array_keys($options), ['fields', 'types', 'transform', 'srcset', 'serialize']) !== []) {
                throw new InvalidArgumentException('Invalid entry field selection. Use native field handles and documented options.');
            }
            foreach (['fields', 'types', 'srcset'] as $key) {
                if (isset($options[$key]) && !is_array($options[$key])) {
                    throw new InvalidArgumentException("Entry field selection option $key must be an array.");
                }
            }
            foreach ($options['types'] ?? [] as $type => $child) {
                if (!is_string($type) || !is_array($child) || array_diff(array_keys($child), ['fields']) !== []
                    || (isset($child['fields']) && !is_array($child['fields']))) {
                    throw new InvalidArgumentException('Matrix type selection requires a native type handle and a fields array.');
                }
            }
            $selection[$handle] = $options;
        }
        return $selection;
    }

    /**
     * @param Entry $entry Owning native entry.
     * @param FieldInterface $field Selected native layout field.
     * @param array<string, mixed> $options Explicit serializer/child/asset options.
     * @param int $depth Current relation depth.
     * @return mixed Finite JSON-compatible public values.
     */
    private function field(Entry $entry, FieldInterface $field, array $options, int $depth): mixed
    {
        $handle = $field->handle;
        if (array_key_exists('serialize', $options)) {
            if (!is_callable($options['serialize'])) {
                throw new InvalidArgumentException("Entry field $handle requires a callable serializer.");
            }
            $value = ($options['serialize'])($entry->getFieldValue($handle), $field, $entry);
            return $this->publicValue($value);
        }

        if (in_array($field::class, [Field\Assets::class, Field\Entries::class, Field\Matrix::class], true)) {
            $elements = $entry->getEagerLoadedElements($handle) ?? $entry->getFieldValue($handle)->all();
            $values = [];
            foreach ($elements as $element) {
                ElementCaches::collectCacheInfoForElement($element);
                if ($field::class === Field\Assets::class && $element instanceof Asset) {
                    $values[] = $this->asset($element, $options);
                } elseif ($element instanceof Entry) {
                    $fields = $options['fields'] ?? [];
                    if ($field::class === Field\Matrix::class && isset($options['types'])) {
                        $type = $element->getType()->handle;
                        if (!array_key_exists($type, $options['types'])) {
                            continue;
                        }
                        $fields = $options['types'][$type]['fields'] ?? [];
                    }
                    $values[] = $this->entry($element, $fields, $depth + 1);
                } else {
                    throw new InvalidArgumentException("Unsupported related element for entry field $handle.");
                }
            }
            return $values;
        }

        // Exact native classes: a third-party subclass must opt in with its own serializer.
        if (!in_array($field::class, [
            Field\PlainText::class, Field\Email::class, Field\Number::class, Field\Range::class,
            Field\Lightswitch::class, Field\Date::class, Field\Time::class, Field\Markdown::class,
            Field\Dropdown::class, Field\RadioButtons::class, Field\ButtonGroup::class,
            Field\Checkboxes::class, Field\MultiSelect::class,
        ], true)) {
            throw new InvalidArgumentException("Unsupported entry field $handle (".$field::class."). Configure an explicit serializer.");
        }
        $value = $entry->getFieldValue($handle);
        return match (true) {
            $value instanceof MarkdownData => HtmlSanitizers::sanitize($value->getHtml()),
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof MultiOptionsFieldData => array_map($this->option(...), iterator_to_array($value)),
            $value instanceof OptionData => $this->option($value),
            default => $this->publicValue($value),
        };
    }

    /**
     * @param Asset $asset Selected native asset.
     * @param array<string, mixed> $options Native transform and optional srcset.
     * @return array<string, mixed> Public native URLs, dimensions and editorial text.
     */
    private function asset(Asset $asset, array $options): array
    {
        $transform = $options['transform'] ?? null;
        return [
            'id' => $asset->id, 'uid' => $asset->uid, 'title' => $asset->title,
            'url' => $asset->getUrl($transform), 'width' => $asset->getWidth($transform),
            'height' => $asset->getHeight($transform), 'alt' => $asset->alt,
            'srcset' => isset($options['srcset']) ? $asset->getSrcset($options['srcset'], $transform) : null,
        ];
    }

    /**
     * @param OptionData $option Selected native choice.
     * @return array{value: ?string, label: ?string} Public choice identity and label.
     */
    private function option(OptionData $option): array
    {
        return ['value' => $option->value, 'label' => $option->label];
    }

    /**
     * @param mixed $value Native scalar or explicit extension result.
     * @param int $depth Current array depth.
     * @return mixed Validated JSON values, with no object or implicit cast.
     */
    private function publicValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 20 || (!is_null($value) && !is_scalar($value) && !is_array($value))
            || (is_float($value) && !is_finite($value))) {
            throw new InvalidArgumentException('An entry field serializer must return finite JSON scalars, arrays or null.');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->publicValue($item, $depth + 1);
            }
        }
        return $value;
    }
}
