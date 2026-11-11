# Automatic entry fields

For ordinary native entry pages, set the section's Template to
`inertia:Site/Articles/Show`. Craft owns its URI, site, publication rules and
preview authorization. The plugin renders `Site/Articles/Show` using the native
matched entry, without a project entry controller or duplicate Laravel route.
The project supplies its Vue page, layout, root, resolver and builds.

Every custom field attached to the entry's field layout is automatically
available under its native handle, for example `entry.fields.summary` or
`entry.fields.heroImage`. Adding a field to the layout requires no plugin
configuration. Craft's existing section and entry-type project-config YAML
already carries the template and layout; there is no extra selector or YAML schema.

This supersedes the earlier component-keyed `pages[component].fields` selection.
That setting is no longer read. All attached fields are sent, including internal
notes and fields hidden by editor layout conditions. Choosing an `inertia:`
template makes that entry's complete field content part of its page response.

## Stable page props

| Prop | Shape |
| --- | --- |
| `entry` | `id`, `uid`, `canonicalId`, `title`, `slug`, `url`, `postDate`, `type`, `fields` |
| `entry.id` | Actual matched element ID, including authorized draft/revision identity. |
| `entry.canonicalId` | Craft canonical identity; it grants no additional access. |
| `entry.type` | Native entry-type handle, including each Matrix child's type. |
| `entry.postDate` | Native date as ISO 8601 with timezone, or null. |
| `entry.fields` | All custom fields in the entry's layout, with native handles. |
| `site` | Native current `id`, `uid`, `handle`, `name`, `language`, `baseUrl`. |
| `preview` | Native matched element's `previewing` flag. |
| `seo` | Native `title`, `canonical` URL and response-policy `robots`. |
| `errors` / page `flash` | Official adapter protocol and native session messages. |

Only the listed base attributes are exported; whole elements, field layouts and
query objects are not dumped. CP/global shares and authentication data remain
outside this renderer. A site's language is not its identity; use Craft's site
ID/handle and native URLs. Vue owns presentation and metadata via Inertia Head.

## Field values

| Native value or field | Page value |
| --- | --- |
| Scalars/nulls | Native strings, numbers, booleans and nulls. |
| Date/time values | ISO 8601 with timezone. |
| Single choice | Selected `{value, label}`. |
| Multiple choices | Native ordered list of selected `{value, label}`. |
| Markdown | Native HTML sanitized with Craft's `HtmlSanitizers`. |
| Link | Native `LinkData::toArray()`, including the resolved URL. |
| Assets | List of `id`, `uid`, `title`, original `url`, `width`, `height`, `alt`, `srcset: null`. |
| Entries | Ordered native visible entries, including all their layout fields. |
| Matrix | Ordered native visible child entries and type identity, including all their layout fields. |
| Other core/custom fields | The field's own `serializeValue(value, entry)` result. |

Relations retain native collection cardinality: an Assets field with one target
is a one-item list and no targets is `[]`. Other relation fields, such as Users,
use their native serialization contract (IDs), rather than exporting user objects.
JSON, Color, Money, Table, Content Block and installed custom fields follow their
own native serializers. No field-specific PHP callback needs to be configured in
the plugin. A custom serializer must return finite JSON scalars, null, arrays,
JSON objects (`stdClass`) or values implementing `JsonSerializable`. Other
objects/resources and excessively nested data cause an explicit error rather than an implicit object dump.

Markdown's demonstrated `v-html` sink applies only to the sanitized Markdown
path. Other strings and custom-field HTML retain the field's own semantics and
should be rendered appropriately by the project.

Entries/Matrix/Assets are eager-loaded through native `Elements` APIs before
projection. Craft's status, site and preview placeholder behavior remains in
force. The matched preview is never requeried as a canonical entry. Each visited
entry/asset is passed to the native active dependency collector; the plugin
starts no cache, collector, queue or invalidation system.

Circular references stop at an entry already present on the current relation
path. That repeated entry retains its base identity and has `fields: []`.
Expansion also stops after ten relation levels. Sibling occurrences are expanded
independently. JSON serialization is bounded at twenty levels.

Assets use their native original URL and dimensions. Automatic entry fields do
not select image transforms or generate a responsive srcset. Project-specific
transforms, protected downloads and uploads retain their native APIs and
permission/action contracts in [FORMS.md](FORMS.md).

## Bespoke pages

Search, filtering, pagination, taxonomy/navigation choices, globals, special
queries and business rules remain project PHP. Continue using explicit native
route/controller destinations and `HandleInertiaRequests` for those pages. Such
destinations take precedence over `inertia:` templates. Their existing explicit
prop contracts are unchanged; the generic entry shape is not forced onto them.

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
