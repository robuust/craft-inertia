# Public page extensions

## Native project props (#14)

Set `publicProps` in the project's `config/craft-inertia.php` to a callable
accepting `Robuust\CraftInertia\Http\PublicPageContext` and returning a string-keyed
array. For example (imports omitted):

```php
'publicProps' => static function (PublicPageContext $context): array {
    $items = Entry::find()->section('navigation')->siteId($context->site->id)->all();
    return ['layout' => [
        'navigation' => array_map(static function (Entry $item): array {
            ElementCaches::collectCacheInfoForElement($item);
            return ['title' => $item->title, 'url' => $item->getUrl()];
        }, $items),
    ]];
},
```

Use `CraftCms\Cms\Entry\Elements\Entry` and
`CraftCms\Cms\Support\Facades\ElementCaches`. Settings/global queries and their
selected public fields also belong in this callback. Collect their dependencies
when participating in native content caching. Never return entire elements,
queries, users, requests or configuration objects.

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

`entry`, `site`, `preview`, `seo`, `errors`, `flash`, and adapter protocol names
listed in `NativePageProps::RESERVED` are reserved, including dotted descendants
(e.g. `seo.title`). Collisions throw an actionable exception. Values must be finite
JSON scalars, null, arrays, `stdClass`, or `JsonSerializable` results, with valid
UTF-8 and at most twenty nested levels. Closures, adapter prop objects, resources
and arbitrary objects are rejected before SSR. JSON-compatible is a format
contract, not permission to publish private data: the project explicitly selects
what it returns. Hook errors propagate; there is no silent omission/fallback.

## Project-owned metadata

`publicProps` is the only page-props extension callback. It accepts arbitrary
project-owned public data, such as navigation, settings or metadata. The plugin
does not interpret added props or define their schema. The native `seo` object
remains exactly `title`, `canonical`, and the renderer's effective `robots`; it
cannot be replaced, extended or shadowed through this hook.

For additional metadata, the project can choose a separate prop name and its own
adapter. For example, with a project-owned `ProjectMetadata` service:

```php
'publicProps' => static function (PublicPageContext $context): array {
    return ['head' => app(ProjectMetadata::class)->forEntry(
        $context->entry, $context->site, $context->request, $context->robots,
    )];
},
```

`head` and `ProjectMetadata` are illustrative project choices. The plugin supplies
no metadata service, tag format, HTML injection or SEO-specific configuration.
The service chooses public values, combines editorial restrictions with the
supplied robots boundary, and projects data for the project's Head/root view.
Initial HTML (with SSR available or unavailable) and subsequent Inertia visits
must render that same project contract. Keep title, canonical and robots tags
unique; preserve stricter editorial directives and never weaken renderer
`noindex, nofollow` denials. The HTTP response policy remains plugin-owned,
including non-200 and partial responses, regardless of added metadata props.
The generic hook cannot inspect or sanitize arbitrary project metadata formats;
its finite-JSON validation does not enforce the project's semantic contract.

SEOmatic integration belongs entirely in the consuming project's adapter. When
installed and enabled there, the adapter can use existing public APIs such as
`previewMetaContainers($uri, $siteId, true, true, $entry)` and
`renderContainersArrayByType()`. Resolve after route selection, pass the actual
matched entry (including an authorized preview) as the fifth argument, and use
the current site/request. Collect metadata once per response and do not cache
output across sites, users or previews. SEOmatic owns its internal lifecycle;
the consuming project owns tag selection, encoding and robots merging.

Craft-inertia imports and requires no SEO plugin and has no separate SEO callback
or SEOmatic adapter. No SEOmatic changes, forks, patches or special release are
needed. In an existing bespoke `data/layout/seo` consumer, keep its metadata
projection in `PageData` and its explicit `PublicRenderer` props closure. That
path already supports project-owned prop names and shapes without changing the
native page contract.

## Migrating an explicit consumer

The additive props callback does not turn a consumer's existing
`data`/`layout`/custom `seo` contract into the native `entry` contract. For example,
robuust-cms's `PageData` selects fields, related datasets, team attributes, link
publication rules and image crops deliberately. Keep its bespoke controller and
`PublicRenderer` while adopting helpers selectively, or deliberately migrate its
frontend and whitelist first. Do not swap it to automatic all-field projection
and accidentally publish fields it currently omits. No consumer changes are
included in this package change.

## Field serializers (#15)

`fieldSerializers` is an ordered array of named registrations, each containing
`matches(FieldContext): bool` and `serialize(FieldContext): mixed`. Register them
in project config (or set that config from a service provider before rendering).
The **first** matching registration wins; there is no implicit class/handle
priority or chained merging. No match uses the native projection unchanged. Invalid
registrations, non-boolean match results and unsupported output throw errors naming
the serializer and field path. Names are diagnostic, not prop names.

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
**project choices**, not package defaults. Imports in these config examples refer
to `Robuust\CraftInertia\Content\FieldContext`, `LinkProjection`, Craft's `Asset`,
`Entry`, and `LinkData`.

```php
'imageProfiles' => [
    'editorialCard' => [
        'transform' => ['width' => 960, 'height' => 540, 'mode' => 'crop', 'format' => 'webp', 'quality' => 80],
        'widths' => [480, 960, 1440],
    ],
],
'fieldSerializers' => [
    'article-cover' => [
        'matches' => static fn (FieldContext $c): bool => $c->entry->getType()->handle === 'article' && $c->field->handle === 'cover',
        'serialize' => static function (FieldContext $c): array {
            $images = [];
            foreach ($c->value as $asset) {
                $images[] = $c->projectAsset($asset, ['editorialCard']);
            }
            return $images;
        },
    ],
    'related-summaries' => [
        'matches' => static fn (FieldContext $c): bool => $c->entry->getType()->handle === 'article' && $c->field->handle === 'related',
        'serialize' => static function (FieldContext $c): array {
            $entries = [];
            foreach ($c->value as $entry) {
                $entries[] = $c->projectEntry($entry, ['summary']);
            }
            return $entries;
        },
    ],
    'published-cta' => [
        'matches' => static fn (FieldContext $c): bool => $c->field->handle === 'cta' && $c->value instanceof LinkData,
        'serialize' => static fn (FieldContext $c): ?array => app(LinkProjection::class)->live($c->value),
    ],
],
```

Without these registrations, `cover` is the original ordered asset list with
`srcset: null`, and `related` includes every attached child field. With them,
`cover` retains **all original keys and values**, adding
`variants.editorialCard.{url,width,height,srcset}`. `related` remains an ordered
list with only each child's `summary` field. Empty relations remain `[]`.
An unregistered Table field still retains native column keys, and Users still
serialize through their native ID contract.

### Example B: directory schema and a custom field

An independent `venue` type has `mapPoint`, a project custom field whose native
`serializeValue()` contract is `['lat' => float, 'lng' => float]` or null, and
`gallery` (Assets). The project deliberately changes its public coordinate shape
and selects a different image policy:

```php
'imageProfiles' => [
    'galleryFit' => [
        'transform' => ['width' => 1200, 'mode' => 'fit', 'format' => 'webp'],
        'widths' => [600, 1200],
    ],
],
'fieldSerializers' => [
    'venue-coordinates' => [
        'matches' => static fn (FieldContext $c): bool => $c->entry->getType()->handle === 'venue' && $c->field->handle === 'mapPoint',
        'serialize' => static function (FieldContext $c): ?array {
            $point = $c->defaultValue();
            return $point === null ? null : [
                'latitude' => round($point['lat'], 5),
                'longitude' => round($point['lng'], 5),
            ];
        },
    ],
    'venue-gallery' => [
        'matches' => static fn (FieldContext $c): bool => $c->entry->getType()->handle === 'venue' && $c->field->handle === 'gallery',
        'serialize' => static function (FieldContext $c): array {
            $images = [];
            foreach ($c->value as $asset) {
                $images[] = $c->projectAsset($asset, ['galleryFit']);
            }
            return $images;
        },
    ],
],
```

Removing the first registration restores the custom field's native `lat`/`lng`
keys; no field is renamed or automatically added. These two schemas illustrate
configuration and resulting contracts; they are not runtime verification claims.

## Native responsive image profiles

Profiles are opt-in on each `AssetProjection::project($asset, $names)` or context
helper call. Merely defining `imageProfiles` transforms nothing. The `transform`
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

`LinkProjection::live(LinkData)` is an explicit opt-in helper, not a change to the
native Link default (`toArray()`). It calls `getUrl(false)` in the existing native
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
An omitted second argument to `entry()` still means **all** attached fields,
including related entries' fields. An explicit root whitelist alone does not
restrict child entries: use a relation serializer with `projectEntry()` and an
explicit child whitelist. Keep project-owned team/user, globals and special
queries explicitly selected, and migrate frontend shapes deliberately.

## Verification boundaries

The package has no CI workflow, test script/configuration, coverage threshold,
frontend build or local application harness. This implementation is checked with
PHP 8.5 syntax lint, host PHP CS Fixer (PSR-12), Composer validation, diff inspection
and source/API review against installed Craft 6 beta.1 / Inertia 3.7. Direct manual
PHP calls also inspected JSON navigation/settings output. Project metadata and
its robots merging remain the consumer's responsibility. Automated tests and
coverage were explicitly excluded for this task and were not run.
A consuming application must still manually verify initial HTML with SSR on/off,
JSON and partial visits, authorized preview, multiple sites, Link publication
changes, SVG/private assets and actual transform/srcset output before rollout.
The configuration examples above do not establish those runtime outcomes.
