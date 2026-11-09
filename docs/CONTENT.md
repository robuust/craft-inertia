# Project content and query conventions

The plugin transports Inertia responses. Content schemas, queries, field adapters
and Vue presentation belong to the consuming project. STEP-02 demonstrates this
with the same unmodified package in two independently named schemas. No package
serializer registry, demo routes, content tables or frontend build is required.

The concrete integration uses Craft `6.0.0-alpha.20`, Laravel `13.40.0`,
`inertiajs/inertia-laravel` `3.7.0` and `@inertiajs/vue3` `3.11.0`.
[STEP-02 evidence](../reports/e2e/STEP02.md) records exactly what was exercised.

## Project extension point

A project controller consumes Craft's `#[CurrentElement] Entry`, checks its
expected section, then calls project PHP projection methods. Each method returns
an explicit array of public scalars, arrays and nulls. The project's field-handle
configuration selects existing fields; it does not define another content model.

The Playground keeps `SiteController::articleSummary()`, `content()`, `richText()`,
`image()` and `blocks()` beside its existing page actions. These small methods are
the extension convention: add a typed method for a new field value and call it
explicitly from the owning page's projection. Extract a project service only when
real callers justify it. The plugin requires no change for a new section, native
field or named third-party adapter.

| Public contract | Project source and fallback |
| --- | --- |
| Existing `title`, `summary`, `body`, `url`, `postDate` | Preserve STEP-01 names. Plain text remains escaped Vue interpolation; missing configured text is `''`. |
| `richText` | Only native `MarkdownData` is accepted here; render with its `getHtml()` and Craft `HtmlSanitizers::sanitize()`. Missing or unsupported values become `''`. |
| `related` | Native Entries field, projected into the existing article summary shape. Missing relation is `[]`. |
| `topics` | Native Entries relation into a project taxonomy section; only public `id`, `title`, `slug`. Missing relation is `[]`. |
| `image` | Native Asset URLs/transforms, `srcset`, transformed dimensions and editorial `alt`; missing asset is `null`. |
| `blocks` | Native Matrix entries, allowlisted project types and explicitly selected `heading`, sanitized `text` and `children`; missing content is `[]`. |
| `globals.tagline` | A live native single in the current site; missing single/field produces `''`. |

In alpha.20, globals and taxonomy use entries: the example uses a `siteSettings`
single and a `topics` section with an Entries field. It does not resurrect Craft 5
GlobalSet, Category or Tag APIs. Taxonomy entries do not need public URLs.

Article config maps `richText`, `relatedArticles`, `topics`, `heroImage` and
`contentBlocks`. The alternate journal maps `richCopy`, `relatedStories`, `themes`,
`coverImage` and `storyBlocks`; its existing `teaser`/`copy` fields still supply
`summary`/`body`. The project page maps `location`, `projectImage`, `projectContent`;
the case-study page maps `city`, `caseImage`, `caseContent`. These changes affect
project config only.

## Native field and asset ownership

Batch relations through native `Elements::eagerLoadElements()` (or query
`with()` for queried collections) before projecting them. Read the native
`getEagerLoadedElements()` collection; do not issue a new relation query for each
item. The example batches direct relations and each nested Matrix level, retaining
Craft's live/publication rules. Do not add `status(null)` to public relations.

The demonstrated Matrix types are `pocTextBlock` and `pocGroupBlock`, using
`heading`, Markdown `blockCopy` and Matrix `nestedContent`. The public property
`children` is deliberately independent of the field handle (`children` is a
reserved Craft field name). Unknown types are omitted; the project projection
bounds nesting to three levels. Extend these decisions in project code when the
project permits other types or depths. Never expose an unknown block's raw fields.

```php
$transform = ['width' => 320, 'height' => 180, 'mode' => 'crop'];
$image = [
    'url' => $asset->getUrl($transform),
    'srcset' => $asset->getSrcset(['320w', '640w'], $transform),
    'width' => $asset->getWidth($transform),
    'height' => $asset->getHeight($transform),
    'alt' => $asset->alt ?? '',
];
```

Craft owns transform generation and filesystem URLs. A cold URL may be Craft's
native transform-generation action before the generated file exists. Tests warm
the native transforms before comparing stable URLs. Vue supplies `sizes` and
renders the received dimensions and alt text; it does not construct transform URLs.
Uploads and protected downloads remain STEP-04.

The only deliberate HTML sinks in the project are sanitized native Markdown
projections. Arbitrary strings, `Htmlable` objects and third-party rich-text data
are not assumed safe or compatible. A named external field needs its own reviewed
project adapter and compatibility evidence in STEP-07.

## Server-owned queries and shareable URLs

The overview accepts `q`, `topic`, `sort` and `page`. The server trims/bounds text,
accepts only `newest`, `oldest`, `title`, normalizes malformed values and restricts
topic filters to live taxonomy entries. Unknown topics return an empty result.
Native `Entry::find()` selects the configured section and current site, uses
`ignorePlaceholders()`, `search()` and field-scoped `relatedTo()`, and applies an
allowlisted order plus an ID tie-breaker.

```php
$total = $query->count();
$page = min(max(1, $requestedPage), max(1, (int) ceil($total / 20)));
$paginator = $query->paginate(20, page: $page, total: $total);
$paginator->appends($filters);
```

This fetches one bounded page through Craft's native paginator. The response
retains `entries` and adds `filters`, public `topics` options and
`pagination.{currentPage,lastPage,total,prevUrl,nextUrl}`. It never serializes the
paginator or the query itself. An unavailable section returns the same empty
contract. Page numbers clamp to the available range; the requested URL remains
shareable and its returned page is explicit in `pagination.currentPage`.

Vue renders the received filters directly. An Inertia `<Form method="get">`
submits named inputs with `preserveState: false`; omitting `page` resets changed
filters to page one. Pagination uses the server's URLs, and Inertia history
restores the server props and form values when going Back. There is no parallel
frontend filter validator, local result cache or manual slicing of all entries.

## Primary API references

The implementation was checked against the installed alpha.20 source, including
[ElementQuery::paginate](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Element/Queries/ElementQuery.php),
[native eager loading](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Element/Concerns/Eagerloadable.php),
[MarkdownData](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Field/Data/MarkdownData.php),
[Matrix](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Field/Matrix.php) and
[Asset transforms](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Asset/Elements/Asset.php).
Inertia's [v3 Form](https://inertiajs.com/docs/v3/the-basics/forms) and
[history](https://inertiajs.com/docs/v3/data-props/remembering-state) contracts
supply navigation behavior; the project adds no custom history implementation.
