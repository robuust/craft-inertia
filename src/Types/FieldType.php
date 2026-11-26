<?php

declare(strict_types=1);

namespace Robuust\CraftInertia\Types;

use CraftCms\Ckeditor\Field as CkeditorField;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\TableCells;

/** Maps known Craft field classes to their native Inertia JSON representation. */
final class FieldType
{
    /**
     * @param FieldInterface $field Resolved field instance from the entry type's layout.
     * @return string TypeScript expression for the native value before any project serializer overrides.
     */
    public function nativeType(FieldInterface $field): string
    {
        return match ($field::class) {
            Field\PlainText::class,
            Field\Email::class,
            Field\Country::class,
            Field\Color::class,
            Field\Icon::class,
            Field\Money::class,
            Field\Date::class,
            Field\Time::class,
            Field\Markdown::class,
            CkeditorField::class => 'string | null',
            Field\Number::class, Field\Range::class => 'number | null',
            Field\Lightswitch::class => 'boolean',
            Field\Json::class => 'JsonValue',
            Field\ButtonGroup::class, Field\Dropdown::class, Field\RadioButtons::class => 'NativeOption',
            Field\Checkboxes::class, Field\MultiSelect::class => 'NativeOption[]',
            Field\Link::class => 'NativeLink | null',
            Field\Assets::class => 'NativeAsset[]',
            Field\Users::class => 'Array<number | null>',
            Field\Matrix::class => $this->matrixType($field),
            Field\Table::class => $this->tableType($field),
            default => $field instanceof Field\Entries && is_a($field::elementType(), Entry::class, true)
                ? 'RelatedEntry[]'
                : 'JsonValue',
        };
    }

    /**
     * @param Field\Matrix $field Matrix field with its configured entry-type usages.
     * @return string Entry list with configured type literals and the generator's fallback.
     */
    private function matrixType(Field\Matrix $field): string
    {
        $handles = array_unique(array_filter(
            array_column($field->getEntryTypes(), 'handle'),
            static fn (?string $handle): bool => $handle !== null && $handle !== '',
        ));
        sort($handles, SORT_STRING);
        $types = $handles === [] ? 'never' : implode(' | ', array_map($this->quote(...), $handles));

        return "MatrixEntry<$types>[]";
    }

    /**
     * @param Field\Table $field Table field whose serializer owns the row structure.
     * @return string Nullable list of serialized rows with native column IDs.
     */
    private function tableType(Field\Table $field): string
    {
        if ($field->columns === []) {
            return 'null';
        }
        if ($field->hasMissingCellTypes()) {
            // Craft returns the normalized rows directly when a configured cell type is missing.
            return 'Array<Record<string, unknown> | unknown[]> | null';
        }

        $columns = [];
        foreach ($field->columns as $id => $column) {
            if ($column['type'] === 'heading') {
                continue;
            }
            $columns[$id] = match ($field->cellType($column)::class) {
                TableCells\Color::class,
                TableCells\Date::class,
                TableCells\Time::class,
                TableCells\Singleline::class,
                TableCells\Multiline::class => 'string | null',
                TableCells\Checkbox::class,
                TableCells\Lightswitch::class,
                TableCells\Email::class,
                TableCells\Number::class,
                TableCells\Money::class,
                TableCells\Select::class,
                TableCells\Url::class => 'string | number | boolean | null',
                TableCells\Link::class => 'Record<string, JsonValue> | JsonValue[] | null',
                default => 'unknown',
            };
        }

        if (array_is_list($columns)) {
            return 'Array<['.implode(', ', $columns).']> | null';
        }

        ksort($columns, SORT_STRING);
        $properties = [];
        foreach ($columns as $id => $type) {
            $properties[] = $this->quote((string) $id).': '.$type;
        }

        return 'Array<{ '.implode('; ', $properties).' }> | null';
    }

    /**
     * @param string $value Native handle or column ID used as a TypeScript literal.
     * @return string Quoted TypeScript literal.
     */
    private function quote(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
