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
use CraftCms\Cms\Support\Facades\HtmlSanitizers;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\HtmlField\HtmlFieldData;
use DateTimeInterface;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Native layout fields with recursive public selection, without internal element attributes. */
final class EntryProjection
{
    /**
     * @param Entry $entry Actual matched/authorized entry; no canonical requery is performed.
     * @param list<string>|null $fields Optional restriction on configured public field handles.
     * @return array<string, mixed> Native identity and the selected field values.
     */
    public function entry(Entry $entry, ?array $fields = null): array
    {
        return $this->project($entry, fields: $fields);
    }

    /**
     * @param Entry $entry Visible native entry.
     * @param array<string, true> $ancestors Entry identities on this relation path.
     * @param int $depth Current relation depth.
     * @param list<string>|null $fields Optional restriction on configured public field handles.
     * @param string $path Diagnostic projection path.
     * @return array<string, mixed> Repeated/deep entries retain identity with empty fields.
     */
    private function project(Entry $entry, array $ancestors = [], int $depth = 0, ?array $fields = null, string $path = 'entry'): array
    {
        ElementCaches::collectCacheInfoForElement($entry);
        $identity = $entry->id !== null ? "$entry->id:$entry->siteId" : 'new:'.spl_object_id($entry);
        $type = $entry->getType()->handle;
        $layout = $entry->getFieldLayout()?->getCustomFields() ?? [];
        $handles = array_map(static fn (FieldInterface $field): string => $field->handle, $layout);
        if ($fields !== null) {
            foreach ($fields as $handle) {
                if (!is_string($handle) || !in_array($handle, $handles, true)) {
                    throw new InvalidArgumentException("$path contains an unknown selected field handle.");
                }
            }
            $layout = array_filter($layout, static fn (FieldInterface $field): bool => in_array($field->handle, $fields, true));
        }
        $selection = self::selection($type, $handles);
        if ($selection !== '*') {
            $layout = array_filter($layout, static fn (FieldInterface $field): bool => in_array($field->handle, $selection, true));
        }
        $layout = isset($ancestors[$identity]) || $depth >= 10 ? [] : $layout;
        $ancestors[$identity] = true;
        $values = [];
        foreach ($layout as $field) {
            $values[$field->handle] = $this->field($entry, $field, $ancestors, $depth, "$path.fields.$field->handle");
        }
        return [
            'id' => $entry->id, 'uid' => $entry->uid, 'canonicalId' => $entry->getCanonicalId(),
            'title' => $entry->title, 'slug' => $entry->slug, 'url' => $entry->getUrl(),
            'postDate' => $entry->postDate?->format(DATE_ATOM),
            'type' => $type, 'fields' => $values,
        ];
    }

    /**
     * @param Entry $entry Native owner entry.
     * @param FieldInterface $field Field selected for public projection.
     * @param array<string, true> $ancestors Entry identities on this relation path.
     * @param int $depth Current entry relation depth.
     * @param string $path Diagnostic field path.
     * @return mixed Validated custom output or unchanged native field projection.
     */
    private function field(Entry $entry, FieldInterface $field, array $ancestors, int $depth, string $path): mixed
    {
        $handle = $field->handle;
        $relation = $field instanceof Field\Assets || $field instanceof Field\Entries || $field instanceof Field\Matrix;
        $value = $relation
            ? ($entry->getEagerLoadedElements($handle) ?? $entry->getFieldValue($handle)->eagerly()->all())
            : $entry->getFieldValue($handle);
        if ($relation) {
            $value = is_array($value) ? array_values($value) : array_values(iterator_to_array($value));
            foreach ($value as $element) {
                ElementCaches::collectCacheInfoForElement($element);
            }
        }
        $native = fn (): mixed => $this->native($entry, $field, $value, $relation, $ancestors, $depth, $path);
        $serializers = config('craft-inertia.fieldSerializers', []);
        if (!is_array($serializers)) {
            throw new InvalidArgumentException('craft-inertia.fieldSerializers must be an ordered array.');
        }
        if ($serializers !== []) {
            foreach ($serializers as $name => $serializer) {
                if (!is_array($serializer) || !is_callable($serializer['matches'] ?? null) || !is_callable($serializer['serialize'] ?? null)) {
                    throw new InvalidArgumentException("Serializer $name must define callable matches and serialize callbacks.");
                }
            }
            $context = new FieldContext(
                $entry,
                $field,
                $value,
                Sites::getCurrentSite(),
                app(Request::class),
                $depth,
                $path,
                $native,
                fn (Entry $child, array $fields): array => $this->project($child, $ancestors, $depth + 1, $fields, "$path.related"),
            );
            foreach ($serializers as $name => $serializer) {
                $matches = $serializer['matches']($context);
                if (!is_bool($matches)) {
                    throw new InvalidArgumentException("Serializer $name matches must return a boolean for $path.");
                }
                if ($matches) {
                    return PublicValue::normalize($serializer['serialize']($context), "$path (serializer $name)");
                }
            }
        }
        return $native();
    }

    /**
     * @param Entry $entry Native owner entry.
     * @param FieldInterface $field Native field definition.
     * @param mixed $value Normalized native value or eagerly loaded relation elements.
     * @param bool $relation Whether the field is a native Assets/Entries/Matrix relation.
     * @param array<string, true> $ancestors Current relation ancestry.
     * @param int $depth Current entry relation depth.
     * @param string $path Diagnostic field path.
     * @return mixed Default native field shape.
     */
    private function native(Entry $entry, FieldInterface $field, mixed $value, bool $relation, array $ancestors, int $depth, string $path): mixed
    {
        if ($relation) {
            $values = [];
            foreach ($value as $element) {
                if ($element instanceof Asset) {
                    $values[] = app(AssetProjection::class)->project($element, $this->imageProfiles($entry, $field));
                } elseif ($element instanceof Entry) {
                    $values[] = $this->project($element, $ancestors, $depth + 1, path: "$path.related");
                } else {
                    throw new InvalidArgumentException("Unsupported related element for $path.");
                }
            }
            return $values;
        }
        return PublicValue::normalize(match (true) {
            $value instanceof MarkdownData => HtmlSanitizers::sanitize($value->getHtml()),
            $value instanceof HtmlFieldData => (string) $value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof MultiOptionsFieldData => array_map($this->option(...), iterator_to_array($value)),
            $value instanceof OptionData => $this->option($value),
            $value instanceof LinkData => app(LinkProjection::class)->live($value),
            default => $field->serializeValue($value, $entry),
        }, $path);
    }

    /**
     * @internal Shared with static entry schema discovery.
     * @param string $type Current root, related or Matrix entry's type handle.
     * @param array<array-key, string> $handles Field handles in the entry layout.
     * @return list<string>|'*' Public handles, or '*' for all attached layout fields.
     */
    public static function selection(string $type, array $handles): array|string
    {
        $selections = config('craft-inertia.entryFields', []);
        if (!is_array($selections)) {
            throw new InvalidArgumentException("craft-inertia.entryFields must map entry-type handles to field lists or '*'.");
        }
        $key = array_key_exists($type, $selections) ? $type : '*';
        if (!array_key_exists($key, $selections)) {
            return [];
        }
        $fields = $selections[$key];
        if ($fields === '*') {
            return $fields;
        }
        if (!is_array($fields) || !array_is_list($fields)) {
            throw new InvalidArgumentException("craft-inertia.entryFields.$key must be a list of attached field handles or '*'.");
        }
        foreach ($fields as $handle) {
            if (!is_string($handle) || !in_array($handle, $handles, true)) {
                throw new InvalidArgumentException("craft-inertia.entryFields.$key contains an unknown field handle for entry type $type.");
            }
        }
        return $fields;
    }

    /**
     * @param Entry $entry Current field owner.
     * @param FieldInterface $field Native Assets field.
     * @return list<string> Project-selected responsive image profiles.
     */
    private function imageProfiles(Entry $entry, FieldInterface $field): array
    {
        $mapping = config('craft-inertia.fieldImageProfiles', []);
        if (!is_array($mapping)) {
            throw new InvalidArgumentException('craft-inertia.fieldImageProfiles must map entry types to Assets field profiles.');
        }
        $fields = $mapping[$entry->getType()->handle] ?? $mapping['*'] ?? [];
        if (!is_array($fields)) {
            throw new InvalidArgumentException('craft-inertia.fieldImageProfiles entry-type values must be field maps.');
        }
        $profiles = $fields[$field->handle] ?? $fields['*'] ?? [];
        if (!is_array($profiles) || !array_is_list($profiles)) {
            throw new InvalidArgumentException('craft-inertia.fieldImageProfiles field values must be profile-name lists.');
        }
        return $profiles;
    }

    /**
     * @param OptionData $option Native selected option.
     * @return array{value: ?string, label: ?string} Native choice identity and label.
     */
    private function option(OptionData $option): array
    {
        return ['value' => $option->value, 'label' => $option->label];
    }
}
