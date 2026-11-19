# Generate TypeScript page props

Use a [pinned candidate](INSTALLATION.md#try-a-pinned-candidate) to try the
unreleased `craft-inertia:types` command. With the plugin installed and enabled,
apply the project's Craft configuration before generating so the command sees
the entry types and field layouts the site uses.

```sh
php artisan craft-inertia:types --no-interaction
```

The default destination is `resources/js/types/craft-inertia.d.ts`. Set
`--output` to use another declaration file. Relative paths start at the
project root. The command marks the declaration as generated and preserves
existing files it does not own.

Import the generated type in a Vue page for an effective entry type such as
`article`. Adjust the import to the location of the declaration file:

```ts
import type { NativePageProps } from '../../types/craft-inertia';

type ArticlePageProps = NativePageProps<'article'>;
```

`NativePageProps` covers `entry`, `site`, `preview`, and `head`. Project-owned
props need their own declarations. The generator uses the same
[`entryFields` selection](CONTENT.md) as public rendering, including explicit
empty lists and wildcard rules. It retains native field handles and respects
the usage handles assigned in sections and Matrix fields. Layouts with the
same effective type handle are represented as alternatives.

## Native fields and relations

The declarations describe serialized values, such as nullable scalars,
date strings, native Link objects, Asset lists and Table rows. Table keys
follow Craft's native column IDs. Unsupported field or cell types are
`unknown`.

Related entries and Matrix children can return `fields: []` when projection
reaches its recursion limit or encounters an ancestor again. For a known
related type, `RelatedEntry<'article'>` includes that entry's selected fields
and the empty projection. Matrix declarations include the configured child
types and a broad entry for historical or dynamically provided children.
Validate the actual shape when narrowing arbitrary relation values. A type
handle alone can correspond to several layouts.

## Custom field output

Set `entryFieldTypes` in `config/craft-inertia.php` to an explicit TypeScript
type expression for an effective entry type and field handle:

```php
'entryFieldTypes' => [
    'article' => [
        'summary' => 'string | { plain: string; html: string }',
        'eventDates' => 'import("./events").EventDate[]',
    ],
],
```

Declare the complete field output, including native fallback and null where
the runtime can return them. An import type is relative to the generated file.
The consuming project's TypeScript compiler checks these expressions with the
rest of its declarations.

The generator leaves runtime serializers unevaluated. Their matching callbacks
can depend on the request, field value, or relation path. With field serializers
configured, any field without explicit metadata is `unknown`, including
otherwise known core fields. Metadata affects declarations for selected fields
only. Review it alongside the field serializer whenever the output changes.

## Check for drift

After a field or entry-type change, apply the project configuration and
regenerate. For CI or before a frontend build, use the same project
configuration and output location:

```sh
php artisan craft-inertia:types --check --no-interaction
```

Check mode compares the expected bytes and exits with a failure status when
the declarations are missing or stale. It does not write files. Run the
consumer's TypeScript or Vue type check after regeneration.
