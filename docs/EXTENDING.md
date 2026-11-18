# Public page extensions

## Native project props (#14)

Set `publicProps` in the project's `config/craft-inertia.php` to a callable
accepting `Robuust\CraftInertia\Http\PublicPageContext` and returning a string-keyed
array. Register a public static method so Laravel's `config:cache` deployment
can export and reload the configuration:

```php
'publicProps' => [\App\PublicFrontend\PublicPageProps::class, 'navigation'],
```

Copy the project-owned [PublicPageProps example](examples/PublicPageProps.php)
into `app/PublicFrontend/PublicPageProps.php` and adapt its native navigation query
to the consuming schema. It selects the current site's entries, collects their
native dependencies, and returns only titles and URLs under `layout.navigation`.
Settings/global queries and their selected public fields also belong in this
project callback. Never return entire elements, queries, users, requests or
configuration objects.

Use cacheable callback references such as `[ProjectClass::class, 'publicStaticMethod']`
or named function strings for all configured hooks. Do not put anonymous
functions, first-class callable closures, or service instances in configuration,
including configuration set during a service provider's `boot()`: Laravel boots
the application before exporting its configuration cache. Resolve project services
inside a named callback when it runs, rather than storing instances in config.
The callback classes must be autoloadable before rebuilding the deployment cache.
The plugin defaults remain `null`/empty arrays and require no project callbacks.

The callback runs once per native page render, inside `PublicRenderer`, after
site/token/entry authorization and the SSR/indexing eligibility decision. Context
contains the **actual** matched `entry` (including authorized draft/revision),
current `site`, current `request`, and renderer `robots` ceiling. It never queries
the canonical entry. No provider result or context is stored on the service or
shared globally; do not capture request-specific data in static provider state.

The same callback resolves for HTML/SSR, SSR-unavailable HTML and Inertia JSON.
On partial visits it still runs once; the official adapter then applies its
normal `only`/`except` selection, including dotted selectors. These are ordinary
props, not deferred, once, merge or always props. CP/global shares stay excluded.
The callback is not applied to bespoke `PublicRenderer` responses, whose explicit
prop contracts remain project-owned.

`entry`, `site`, `preview`, `head`, `errors`, `flash`, and adapter protocol names
listed in `NativePageProps::RESERVED` are reserved, including dotted descendants
(e.g. `head.title`). Collisions throw an actionable exception. Values must be finite
JSON scalars, null, arrays, `stdClass`, or `JsonSerializable` results, with valid
UTF-8 and at most twenty nested levels. Closures, adapter prop objects, resources
and arbitrary objects are rejected before SSR. JSON-compatible is a format
contract, not permission to publish private data: the project explicitly selects
what it returns. Hook errors propagate; there is no silent omission/fallback.

## Metadata and Head

The generic `head` callback accepts project-calculated title, meta/link attribute
maps and raw JSON-LD values. The plugin supplies entry defaults, normalizes the
contract, combines editorial restrictions with its robots policy and renders
matching Vue/Blade Head output. It has no third-party metadata-plugin dependency,
detection or adapter. See [HEAD.md](HEAD.md) for the contract and setup.

## Declarative native field selection

Ordinary entry pages need no project controller or field serializer. Select
which fields to publish in `entryFields`, keyed by **entry-type handle** for
root entries, related entries and Matrix children.

```php
'entryFields' => [
    'article' => ['summary', 'body', 'cover', 'related', 'cta', 'blocks'],
    'teaser' => ['summary', 'cover'],
    'textBlock' => ['text'],
],
'fieldImageProfiles' => [
    'article' => ['cover' => ['editorialCard']],
    'teaser' => ['cover' => ['editorialCard']],
],
```

Each entry uses its type's rule first, including `[]`, then the wildcard rule
if configured. An empty list or no matching rule yields identity with
`fields: []`. Lists must name attached fields of the projected type. A wildcard
list must be valid for each type that uses it.

Set an entry-type rule to the string `'*'` to include all its attached fields.
Set `entryFields` to `['*' => '*']` to retain the earlier default for every
unmatched type, as in beta.3, 1.0.0 and 1.0.1. These rules also expose fields hidden by editor layout
conditions and any fields later added to the layout. See the
[upgrade guide](UPGRADE.md) when moving to 1.0.2.

An explicit `EntryProjection::entry($entry, $fields)` selection narrows the
configured fields to the intersection. Related children still apply their own
configured rules when the projector handles them. Review the values returned
by selected fields that use native composite serializers, by your field
serializers, and by your `publicProps` and `head` callbacks and bespoke pages.
These can publish nested values beyond the fields selected on projected
entries. See [field values](CONTENT.md#field-values).

`fieldImageProfiles` maps type handles to Assets field handles to profile-name
lists. `'*'` is the fallback at either map level. A matching type's map replaces
the wildcard type map. The plugin applies these profiles to ordinary native
Assets relations; the site chooses crops/sizes without writing a serializer.
A definition under `imageProfiles` alone still transforms nothing.

## Migrating an explicit consumer

Make the native plugin contract the starting point: `entry/site/preview/head`,
with selected custom values under `entry.fields`. Map ordinary sections to
`inertia:pages/work/[slug]` or another actual Vue component name. Craft keeps its
native URL/site/publication/preview resolution; no duplicate Laravel route or
page controller is needed.

Before changing routes, configure the public field list for each entry type used
by the page, including related entries and Matrix children. Choose
native image profiles with `fieldImageProfiles`; consume their `variants` in the
frontend. Link fields now return the publication-aware selected shape by default,
and native HTML field data renders its references before serialization.

Move navigation, globals, selected queries, filters, user/team selection and form
props to project callbacks/controllers. Move external metadata calculation to the
project's generic `head` provider and use the packaged Head components. No
third-party adapter is part of this plugin. Migrate the old `data/layout/seo`
envelope and frontend prop access deliberately while keeping existing design,
HTML/CSS and order. Retain bespoke controllers for actual search/filter/error or
other project behavior. This package does not modify any consumer.

## Field serializers (#15)

`fieldSerializers` is an ordered array of named registrations, each containing
`matches(FieldContext): bool` and `serialize(FieldContext): mixed`. Register them
as public static callback references in project config, just like `publicProps`.
The **first** matching registration wins; there is no implicit class/handle
priority or chained merging. No match uses the native projection unchanged. Invalid
registrations are all checked before any match callback is evaluated for a
projected field, including registrations after a potential first match. A
non-boolean result from an evaluated match callback or unsupported serializer
output throws an error naming the serializer and field path. Names are diagnostic, not prop names.

`FieldContext` contains the owner `entry`, native `field`, normalized `value`,
current `site` and `request`, relation `depth` and diagnostic `path`. For native
Assets/Entries/Matrix, `value` is an ordered list of already resolved native elements,
not the query object. Existing eager loads are reused; otherwise Craft's native
`eagerly()->all()` batches siblings. Native site, publication and preview
placeholder behavior remains intact. Match predicates should be side-effect free.
No context or result is retained between requests.

Helpers:

- `defaultValue()` invokes this field's native projection without re-entering its
  serializer selector. Child fields still use the normal serializer pipeline.
- `projectEntry($entry, ['summary'])` retains native identity and includes
  `summary` when the child's configured rule selects it. `[]` (the default)
  returns identity with empty fields. It shares the current relation ancestry
  and depth limit. Unknown handles fail.
- `projectAsset($asset, ['card'])` returns the original asset plus selected image
  variants. `[]` returns the unchanged native asset shape.
- `collect($element)` adds an extra queried element to Craft's active dependency
  collector. It does not create a collector or cache.

Every owner and direct native related element is collected even when a serializer
skips its native output. Helpers collect projected entries/assets; projects must
collect additional elements they query or otherwise use. Do not construct new
unrestricted queries from relation IDs, requery canonical previews, call a fresh
root projector to circumvent recursion guards, or return raw elements/users.
Circular paths retain identity with `fields: []`; expansion stops at ten entry
relation levels. Custom output uses the same twenty-level, finite JSON validator
as public props. Each field retains its original handle. A deliberate override
owns its new shape/cardinality; ordinary relations remain ordered lists.

### Example A: magazine schema

This project's `article` type has `summary` (text), `cover` (Assets), `related`
(Entries) and `cta` (Link). Other selected fields keep their native
serialization. Sizes below are project choices. Copy the project-owned
[ArticleSerializers example](examples/ArticleSerializers.php) into
`app/PublicFrontend/ArticleSerializers.php` before configuring these callbacks.

```php
'entryFields' => [
    'article' => ['summary', 'cover', 'related', 'cta'],
],
'imageProfiles' => [
    'editorialCard' => [
        'transform' => ['width' => 960, 'height' => 540, 'mode' => 'crop', 'format' => 'webp', 'quality' => 80],
        'widths' => [480, 960, 1440],
    ],
],
'fieldSerializers' => [
    'article-cover' => [
        'matches' => [\App\PublicFrontend\ArticleSerializers::class, 'matchesCover'],
        'serialize' => [\App\PublicFrontend\ArticleSerializers::class, 'cover'],
    ],
    'related-summaries' => [
        'matches' => [\App\PublicFrontend\ArticleSerializers::class, 'matchesRelated'],
        'serialize' => [\App\PublicFrontend\ArticleSerializers::class, 'related'],
    ],
    'published-cta' => [
        'matches' => [\App\PublicFrontend\ArticleSerializers::class, 'matchesCta'],
        'serialize' => [\App\PublicFrontend\ArticleSerializers::class, 'cta'],
    ],
],
```

Without the serializer registrations, the configured `cover` is the original
ordered asset list with `srcset: null`, and `related` includes each child's
configured fields. Links are publication-aware by default. With the registrations,
`cover` adds `variants.editorialCard.{url,width,height,srcset}` to each original
asset. `related` remains an ordered list and includes only each child's `summary`
when selected by its type rule. Empty relations remain `[]`. When selected, an
unregistered Table field retains native column keys and Users serialize to IDs.

### Example B: directory schema and a custom field

An independent `venue` type has `mapPoint`, a project custom field whose native
`serializeValue()` contract is `['lat' => float, 'lng' => float]` or null, and
`gallery` (Assets). The project deliberately changes its public coordinate shape
and selects a different image policy. Copy the project-owned
[VenueSerializers example](examples/VenueSerializers.php) into
`app/PublicFrontend/VenueSerializers.php` before using this configuration:

```php
'entryFields' => [
    'venue' => ['mapPoint', 'gallery'],
],
'imageProfiles' => [
    'galleryFit' => [
        'transform' => ['width' => 1200, 'mode' => 'fit', 'format' => 'webp'],
        'widths' => [600, 1200],
    ],
],
'fieldSerializers' => [
    'venue-coordinates' => [
        'matches' => [\App\PublicFrontend\VenueSerializers::class, 'matchesCoordinates'],
        'serialize' => [\App\PublicFrontend\VenueSerializers::class, 'coordinates'],
    ],
    'venue-gallery' => [
        'matches' => [\App\PublicFrontend\VenueSerializers::class, 'matchesGallery'],
        'serialize' => [\App\PublicFrontend\VenueSerializers::class, 'gallery'],
    ],
],
```

Removing the first registration restores the custom field's native `lat`/`lng`
keys; no field is renamed or automatically added. These two schemas illustrate
configuration and resulting contracts; they are not runtime verification claims.

## Native responsive image profiles

Profiles are selected through native `fieldImageProfiles` configuration or an
explicit `AssetProjection::project($asset, $names)` / context helper call. Merely
defining `imageProfiles` transforms nothing. The `transform`
is a native named transform handle or configuration array; `widths` is an optional
list of positive integer widths. Unknown profiles and invalid definitions fail
clearly. Craft owns further transform validation, focal points, cropping, format,
quality, generation and transform-cache behavior.

The helper calls Craft 6's `$asset->transform($definition)` and
`$asset->getSrcset(['600w', '1200w'], $definition)`. It uses the returned native URL
and dimensions, without deriving URLs or implementing a crop engine. No transform
is applied to SVG/non-image assets or to an asset whose original public URL is
null. Such selected assets retain their original data and have `variants: []`.
This avoids introducing a usable transform action URL for an originally non-public
source. Profiles never change top-level `id`, `uid`, `title`, `url`, dimensions,
editorial `alt` or `srcset: null`. No profile names, sizes or crop settings ship in
the generic package.

## Publication-aware Link helper

`LinkProjection::live(LinkData)` is the native Link default and is also available
for selected project queries. It calls `getUrl(false)` in the existing native
site/preview context, preserving the complete URL, homepage normalization,
URL suffix and browsing target. An empty/unavailable URL returns null. Available
links expose `url`, `label`, `target`, `urlSuffix` and native `attributes` with the
checked `href`. It does not expose the native permissive `value`, rendered `link`
or linked element metadata, which could reveal a blocked destination. The linked
element is dependency-collected. It does not add a second generic button format.
A consumer keeping `[{text,url,target}]` maps these selected values explicitly.

## Reusing helpers in bespoke pages

A consumer can use `EntryProjection::entry($entry, ['heading', 'image'])`,
`AssetProjection::project($asset, ['galleryFit'])`, and `LinkProjection::live($link)`
within its own `data/layout/seo` envelope. `entry()` uses the configured selection
and intersects it with the optional handles passed as its second argument. A
missing rule yields `fields: []` even when the caller supplies handles. Each child
resolves its own rule, and a relation serializer can narrow that selection
through `projectEntry()`. Keep project-owned team/user, globals and special
queries explicitly selected, and migrate frontend shapes deliberately.

## Verification boundaries

Package checks cover PHP syntax/formatting, Composer validation, Vue
lint/client/SSR compilation and source/diff review. In the consuming project,
verify that initial page data and Inertia JSON include selected fields and omit
unselected fields on roots, related entries and Matrix children. Include a field
added to a layout with an existing allowlist. Verify initial HTML with SSR on/off,
partial visits, preview identity, multiple sites, Link publication changes,
SVG/private assets, image transforms and actual metadata before rollout.
