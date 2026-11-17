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

Ordinary entry pages need no project controller or field serializer. Configure
`entryFields` by **entry-type handle**, including Matrix child types, when a site
needs a public whitelist. `'*'` supplies the fallback for unmatched types:

```php
'entryFields' => [
    '*' => [],
    'article' => ['summary', 'body', 'cover', 'related', 'cta'],
    'teaser' => ['summary', 'cover'],
],
'fieldImageProfiles' => [
    'article' => ['cover' => ['editorialCard']],
    'teaser' => ['cover' => ['editorialCard']],
],
```

Every root, related entry and Matrix child applies its own type's selection.
`[]` means identity with empty fields. With empty `entryFields` config, the
existing all-layout-fields behavior remains; with no matching type or `'*'`
rule, all attached fields are still included. Use `'*' => []` when migrating an
existing selected-field consumer, so a newly introduced child type cannot
silently publish its fields. Selection lists must name attached fields; invalid
handles fail explicitly. Editor layout visibility conditions are not a public
access boundary.

An explicit `EntryProjection::entry($entry, $fields)` selection can narrow the
configured fields further, but cannot broaden them. Related children still apply
their own configured rules. Custom serializers remain deliberate project-owned
code; they must not return private data under another key.

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

Before changing routes, configure the existing public field whitelist by entry
type, with an identity-only `'*'` fallback and independent child rules. Choose
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
- `projectEntry($entry, ['summary'])` retains native identity and selects only the
  listed attached fields. `[]` (the default) returns identity with empty fields.
  It shares the current relation ancestry and depth limit; unknown handles fail.
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

This project's `article` type has `cover` (Assets), `related` (Entries) and
`cta` (Link). Every other field keeps its native serialization. Sizes below are
**project choices**, not package defaults. Copy the project-owned
[ArticleSerializers example](examples/ArticleSerializers.php) into
`app/PublicFrontend/ArticleSerializers.php` before configuring these callbacks.

```php
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

Without these registrations or declarative field/image selections, `cover` is
the original ordered asset list with `srcset: null`, and `related` includes every
attached child field. Links are publication-aware by default. With them,
`cover` retains **all original keys and values**, adding
`variants.editorialCard.{url,width,height,srcset}`. `related` remains an ordered
list with only each child's `summary` field. Empty relations remain `[]`.
An unregistered Table field still retains native column keys, and Users still
serialize through their native ID contract.

### Example B: directory schema and a custom field

An independent `venue` type has `mapPoint`, a project custom field whose native
`serializeValue()` contract is `['lat' => float, 'lng' => float]` or null, and
`gallery` (Assets). The project deliberately changes its public coordinate shape
and selects a different image policy. Copy the project-owned
[VenueSerializers example](examples/VenueSerializers.php) into
`app/PublicFrontend/VenueSerializers.php` before using this configuration:

```php
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
without changing its `data/layout/seo` envelope or using native all-field pages.
An omitted second argument to `entry()` uses the configured type selection, or
**all** attached fields when no selection applies. Every child independently
applies `entryFields`; use an identity-only wildcard rule to avoid accidental
child-field exposure. A relation serializer can narrow children further through
`projectEntry()`. Keep project-owned team/user, globals and special
queries explicitly selected, and migrate frontend shapes deliberately.

## Verification boundaries

The package has no CI workflow, test script/configuration, coverage threshold or
local application harness. Package checks cover PHP syntax/formatting, Composer
validation, Vue lint/client/SSR compilation and source/diff review. Automated
tests and coverage are explicitly excluded for this task. Consumer runtime
acceptance remains separate: manually verify initial HTML with SSR on/off,
JSON/partial visits, preview identity, multiple sites, Link publication changes,
SVG/private assets, image transforms and actual metadata before rollout.
