# Public content and field selection

For ordinary native entry pages, set the section's Template to
`inertia:Site/Articles/Show`. Craft owns its URI, site, publication rules and
preview authorization. The plugin renders `Site/Articles/Show` using the native
matched entry, without a project entry controller or duplicate Laravel route.
The project supplies its Vue page, layout, root, resolver and builds.

Select fields in `config/craft-inertia.php`. The component key is exact and
case-sensitive; field handles retain their native names:

```php
return [
    'rootView' => 'frontend::app',
    'indexingPolicy' => null,
    'pages' => [
        'Site/Articles/Show' => ['fields' => [
            'summary', 'body',
            'heroImage' => [
                'transform' => ['width' => 320, 'height' => 180, 'mode' => 'crop'],
                'srcset' => ['320w', '640w'],
            ],
            'relatedArticles' => ['fields' => ['summary']],
            'contentBlocks' => ['types' => [
                'textBlock' => ['fields' => ['heading', 'blockCopy']],
                'groupBlock' => ['fields' => [
                    'heading', 'nestedContent' => ['fields' => ['heading', 'blockCopy']],
                ]],
            ]],
        ]],
    ],
];
```

This selects values from Craft's schema; it does not define another schema.
`heroImage` is returned as `entry.fields.heroImage`, never renamed to `image`.
Without page configuration, `entry.fields` is empty. A selected handle absent
from the entry's layout is omitted. Present nullable native values remain null;
relations retain native collection cardinality, including `[]` for no targets.
An Assets field with one target still returns a one-item list. Ordering comes
from Craft. No whole-element or whole-field-layout serialization is used.

## Stable page props

| Prop | Shape |
| --- | --- |
| `entry` | `id`, `uid`, `canonicalId`, `title`, `slug`, `url`, `postDate`, `type`, `fields` |
| `entry.id` | Actual matched element ID, including authorized draft/revision identity. |
| `entry.canonicalId` | Craft canonical identity; it grants no additional access. |
| `entry.type` | Native entry-type handle, including each Matrix child's type. |
| `entry.postDate` | Native date as ISO 8601 with timezone, or null. |
| `entry.fields` | Only explicitly selected native handles, at every level. |
| `site` | Native current `id`, `uid`, `handle`, `name`, `language`, `baseUrl`. |
| `preview` | Native matched element's `previewing` flag. |
| `seo` | Native `title`, `canonical` URL and response-policy `robots`. |
| `errors` / page `flash` | Official adapter protocol and native session messages. |

There are no CP props, users, credentials, entry-layout objects, query objects,
translation queries, article navigation, global tagline or inferred description.
Vue owns presentation and metadata via official Inertia Head. A site's language
is not its identity; use Craft's site ID/handle and native URLs.

## Supported core fields

| Native field | Public value |
| --- | --- |
| PlainText, Email | Native string/null, rendered escaped by Vue. |
| Number, Range, Lightswitch | Native numeric/boolean/null values. |
| Date, Time | ISO 8601 date/time with timezone, or null. |
| Dropdown, RadioButtons, ButtonGroup | Selected `{value, label}`. |
| Checkboxes, MultiSelect | Native ordered list of selected `{value, label}`. |
| Markdown | `MarkdownData::getHtml()`, then Craft `HtmlSanitizers::sanitize()`. |
| Assets | List of `id`, `uid`, `title`, `url`, `width`, `height`, `alt`, `srcset`. |
| Entries | List of safe base entry props plus selected child `fields`. |
| Matrix | Native ordered child entries and type identity, with selected child `fields`. |

Only the named native classes are automatic. Third-party subclasses and other
core types (including Users, JSON, Table, Link, Color, Money and Addresses) need
an explicit serializer. Unknown field types fail with a configuration error
naming the handle/class, without including its value. Unknown objects never
silently become strings or arrays. Strings are not assumed to be safe HTML.
Only the native Markdown path is suitable for the demonstrated `v-html` sink.

Direct selected relations are eager-loaded through native `Elements` APIs before
projection. Each selected nested level is loaded in turn; public queries retain
Craft's status/placeholder behavior. The matched preview is never requeried as a
canonical entry. Every visited element is passed to native
`ElementCaches::collectCacheInfoForElement()` for an already active collector.
The plugin starts no cache, collector, queue or invalidation system.

`fields` selects common child fields. Matrix may instead use `types`, keyed by
native type handle; unlisted types are omitted. A listed type with no `fields`
has base props only. Without `types`, all native visible children have their base
props and selected common fields. Nesting is explicitly bounded by configuration
and capped at ten relation levels. Do not create an automatic recursive selector.

Asset `transform` accepts Craft's native transform value (an array or transform
handle); omit it for the original. `srcset` is opt-in and uses Craft's native
`getSrcset()`. URLs, dimensions, transforms, filenames and alt remain Craft-owned;
no filesystem path is projected. A cold transform URL may point to Craft's normal
generation action. Projects choose Vue `sizes` and CSS. Protected downloads and
uploads still require their existing permission/action contracts in [FORMS.md](FORMS.md).

## Custom fields and bespoke pages

A selected field can provide a serializable static callable:

```php
'customField' => ['serialize' => [App\Content\PublicCustomField::class, 'serialize']],
```

The callable receives `(mixed $value, FieldInterface $field, Entry $entry)`.
It must return finite JSON scalars, null or arrays of those values, with at most
20 nested array levels. The plugin rejects objects, resources and non-finite
numbers even when returned by an extension. The project owns the callable's
privacy, sanitization, authorization and any queried element dependencies.
Use a class/static method for cached configuration; closures do not survive
Laravel configuration caching. This is one field-level callback, not a registry
or automatic support claim for an installed plugin.

Search, filtering, pagination, taxonomy/navigation choices, globals, special
queries and business rules remain project PHP. Continue using existing explicit
native route/controller destinations and `HandleInertiaRequests` for these
pages. Such destinations take precedence over `inertia:` templates. Their
existing prop contracts are unchanged; the generic entry shape is not forced
onto manual integrations.

[STEP-02](../reports/e2e/STEP02.md) and [STEP-03](../reports/e2e/STEP03.md) retain
historical evidence for the local Playground's explicit projections across two
schemas, native pagination, globals, responsive images and editor workflows.
That project convention may still be used for bespoke pages. It is no longer a
requirement to reimplement generic entry serialization in every application.

The current native-template target is official Craft `6.0.0-beta.1`, source
`05ce9b81be3704fb27da3a5a6670accfa49ec9a9`, using its native
[Entry route](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Entry/Elements/Entry.php),
[SetRoute hook](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Element/Concerns/HasRoutesAndUrls.php)
and [ControllerRoute](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Route/ControllerRoute.php).
