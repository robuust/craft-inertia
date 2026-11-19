<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Types;

use CraftCms\Cms\Entry\Data\EntryType;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\Matrix;
use CraftCms\Cms\Support\Facades\EntryTypes;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sections;
use Robuust\CraftInertia\Content\EntryProjection;

/** Reads projected entry layouts from the applied Craft schema. */
final class EntrySchema
{
    /**
     * @return array<string, list<array<string, FieldInterface>>> Selected fields by effective entry type and layout variant.
     */
    public function fieldsByType(): array
    {
        $types = EntryTypes::getAllEntryTypes()->all();
        foreach (Sections::getAllSections() as $section) {
            array_push($types, ...$section->getEntryTypes());
        }
        foreach (Fields::getFieldsByType(Matrix::class, false) as $matrix) {
            array_push($types, ...$matrix->getEntryTypes());
        }

        usort($types, static function (EntryType $a, EntryType $b): int {
            return strcmp($a->handle, $b->handle) ?: $a->id <=> $b->id;
        });

        $fieldsByType = [];
        foreach ($types as $type) {
            $layout = $type->getFieldLayout()->getCustomFields();
            $handles = array_map(static fn (FieldInterface $field): string => $field->handle, $layout);
            $selection = EntryProjection::selection($type->handle, $handles);
            $fields = [];
            foreach ($layout as $field) {
                if ($selection === '*' || in_array($field->handle, $selection, true)) {
                    $fields[$field->handle] = $field;
                }
            }
            ksort($fields, SORT_STRING);
            $fieldsByType[$type->handle][] = $fields;
        }

        return $fieldsByType;
    }
}
