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

## Native sites and preview

A site is identified by Craft ID/UID/handle, with its language and native base
URL. Two sites may share a language and still have different content, domains
or subdirectory roots. Read `Sites::getCurrentSite()` after Craft's middleware;
never choose a site from the language or a public draft/preview query parameter.

The project registers ordinary GET/HEAD Laravel routes for each configured site.
The Playground's `site-content.routes` is project configuration for these route
groups: `domain`, `prefix`, route-name prefix `name`, and the `articles` and
`projects` paths. Domain-specific groups precede unrestricted groups. Section
site settings use the matching native named destination, URI and site. No
package site registry or fallback dispatcher is added. For example, a Dutch
site can register `/nl/artikelen/{slug}` with destination
`dutch.site.articles.show`; another Dutch site can use its own domain and the
same translated path with a different route name.

Use `Url::siteUrl()` for homepage and overview links and the matched element's
`getUrl()` for detail links. These native helpers retain the selected site's
base path and domain. Native pagination starts from the requested overview URL
and retains its filters. Keep the project's asset URL request-relative (or use
an intentionally configured CDN with appropriate origin support); a hardcoded
single-site asset host can prevent JavaScript modules loading on another domain.

The example adds `site.{id,uid,handle,baseUrl,links}` to the existing `name` and
`language`. `site.links` contains only public translations of the current
canonical entry, or the other sites' overview URLs on the overview. Translation
queries select canonical ID plus site ID, keep normal live status, and use
`ignorePlaceholders()`. A missing/unavailable translation is omitted. The project
removes preview/query parameters from those public destination URLs and uses
ordinary anchors for site changes, so cross-origin navigation loads a document.
Fields, relation targets, globals and Matrix propagation/translation remain
native schema decisions. Same language does not mean shared translation values.

Craft's `HandleTokenRequest` validates tokens and prepares native placeholder
elements, including drafts, user provisional drafts and revisions. It removes
preview markers from the prepared query before page dispatch. The project reads
`#[CurrentElement] Entry` and `$entry->previewing`, which survive this preparation.
`Request::isPreview()` can be false at that point; marker presence, absence or a
draft/revision ID is not a replacement authority check. `status(null)` belongs
only in Craft's authorized preview preparation, never in a public page query.
The package has no token checker or draft storage.

Both initial HTML and Inertia JSON retain the middleware's `private, no-store`
policy. The native token may remain in Inertia's returned browser URL even when
it was removed from the prepared query; those are separate contracts.

In pinned alpha.20, the ordinary Vue editor's **View** control opens a native
site preview in a popup; it does not expose an embedded Live Preview control.
The separate native `/admin/preview/...` legacy editor refreshes the project Vue
iframe after autosave. Its strict browser-error check still fails: native code
runs an author-selector initializer whose sidebar markup was not inserted.
Functional iframe refresh is verified separately from that failure. No vendor
patch, exception suppression or expected-failure classification is included.
See [STEP-03 evidence and acceptance blocker](../reports/e2e/STEP03.md).
