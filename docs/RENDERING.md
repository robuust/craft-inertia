# Public rendering and response policies

The [installation checklist](INSTALLATION.md) describes project-owned setup and
native lifecycle behavior. CP pages must stay outside the public SSR resolver.
Consuming projects own verification of the privacy policies below; the plugin
contains no test harness. Earlier STEP records remain historical.

STEP-06A, STEP-06B and STEP-06C are human-authorized independent parts of STEP-06.
STEP-05 remains open, including its native account blockers. These slices add
guest SSR, metadata, errors/maintenance, asset versions, indexing/JSON-LD and
native public content-data caching. Account behavior, HTTP output caching and
full AC-06 remain incomplete. See [STEP-06A](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP06A.md),
[STEP-06B](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP06B.md) and [STEP-06C evidence](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP06C.md).

## Native entry-template renderer

An `inertia:` entry page uses the plugin controller through Craft's existing
site fallback and `ControllerRoute`; there is no second catch-all URL registry.
The controller delegates to `PublicRenderer`, which runs the public adapter
around response rendering, since native fallback dispatch does not execute
hypothetical Laravel route middleware.
It uses the configured public root and Vite manifest version, and applies
private/no-store, `Vary: X-Inertia` and default-deny indexing to HTML and JSON.
Explicit indexing policy still requires strict `true`; private/auth/token/preview
or session-form-state contexts remain denied regardless of that callback.

Native mapped pages may use SSR when official `inertia.ssr.enabled` is true and
the host gateway permits it. Guest eligibility excludes native authentication,
consumed route/site tokens, preview state/markers/headers, old input, errors and
messages/flash. The plugin does not set a global SSR callback or replace HTTP
transport. For each response it clones the resolved gateway, retaining host
callbacks and path exclusions. For a private response it disables SSR through `DisablesSsr`,
then restores the original gateway in `finally`. A custom gateway must implement
that official capability. Host disable/configure callbacks and
transport remain intact. Config alone cannot override a host disable callback;
the conservative private boundary therefore operates on the response's gateway.

The renderer also clones the official response factory and clears its shared
props for this response, preventing host/CP globals from entering public pages.
The original factory, shares and SSR gateway are restored even on projection
failure. Public props are all attached layout fields plus official errors
and native flash; no account adapter is introduced. The project owns its Blade
root, Vue Head/layout, resolver, matching client/SSR bundles and service operation.
When enabling a project SSR bundle, exclude native CP/action paths once through
official `Inertia::withoutSsr()` in the host provider, or retain the host's
existing appropriate exclusion. Craft beta.1 otherwise sends CP components to
the same gateway; the public resolver cannot render them. The plugin does not
replace that host policy. The
[provider example](../README.md#configure-the-consuming-project) derives
absolute CP exclusions from Craft's URL API. A dedicated CP origin must be
configured through `baseCpUrl` when `cpTrigger` is null, so the public website
does not inherit a `/*` exclusion. No entry-specific callback is needed.
Public exception presentation remains an optional project concern; configuration
errors use normal application exception handling and do not serialize values.

## Shared public renderer

`Robuust\CraftInertia\Http\PublicRenderer` is the common boundary for native
entry templates and explicit project controllers. Laravel can resolve it with
`app(PublicRenderer::class)`; no project binding is required. Its contract is:

```php
render(
    Request $request,
    string $component,
    Closure $props, // Closure(string $robots): array<string, mixed>
    ?Entry $entry = null,
    int $statusCode = 200,
    array $headers = [],
): Response
```

Call it after `web` and `craft` have resolved the session, site and native token
context. It accepts site page requests; CP/action requests are rejected without
replacing their native handlers. Do not add another Inertia protocol middleware
around it. It does not register a route, resolve or authorize an entry, requery
canonical content, or change precedence of explicit Craft destinations.

The props callback runs inside the cloned response factory and SSR gateway,
after the public root view, asset version and official errors/flash integration
are configured. It receives the effective robots directive, computed before
props or rendering can consume flash. Use that value for SEOmatic or another
project metadata projection; the response uses the same decision. Final non-200
responses, including official version conflicts, always get deny headers.
No global/CP shares or automatic entry fields are added to explicit project
responses. Only the native controller projects the existing `entry`, `site`,
`preview` and `seo` defaults. Each render also gets a fresh official `SsrState`,
so identical page data cannot reuse another render's cached SSR output or bypass
the current gateway's privacy decision. The factory, gateway and original SSR
state are restored in `finally`, including failures in policy evaluation,
projection, view rendering or SSR.
The renderer stores no entry, site, preview or policy decision on its instance.

Public SSR and indexing require an explicitly supplied live entry for the current
site. Previews remain renderable using Craft's actual authorized entry, while
SSR and indexing stay disabled. Missing context, drafts/revisions, disabled or
unpublished entries, non-200 page statuses, CP/actions, unsafe methods, native
authentication, route/site tokens, preview markers/headers and session errors,
messages, old input or Inertia flash are ineligible. These checks are shared
through `HandleEntryRequests::isPublicGuest()`. They restrict rendering policy;
Craft and the project still own permission to access and project content.
Official SSR configuration, path exclusions and host disable/request callbacks
remain effective. Eligibility alone never enables SSR against those settings.

### Migrate a selected project controller

Keep the existing section-to-component map and selected `data`/`layout`/`seo`
projection. Replace the copied factory/gateway swapping and middleware invocation:

```php
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\CurrentElement;
use Illuminate\Http\Request;
use modules\site\services\PageData;
use Robuust\CraftInertia\Http\PublicRenderer;
use Symfony\Component\HttpFoundation\Response;

public function __invoke(Request $request, #[CurrentElement] Entry $entry): Response
{
    return app(PublicRenderer::class)->render(
        $request,
        PageData::COMPONENTS[$entry->getSection()->handle],
        fn (string $robots): array => (new PageData())->props($entry, $robots),
        $entry,
    );
}
```

In this migration, update the project's `PageData::props()` and SEO projection to
accept `string $robots`. Use it to replace any robots meta in the selected SEO
shape, instead of calling another rendering predicate. Keep field selection,
queries, layouts, images, SEOmatic and authorization in the project. Move the
projection into the callback rather than evaluating it before `render()`.

Explicit component names such as `pages/work/[slug]` are passed through the host
resolver. Native `inertia:` template components still accept only slash-separated
letters, numbers, underscores and hyphens. Moving this project to automatic entry
templates requires separately deciding component naming and the wider automatic
field payload; this renderer does not make that switch. Issue #14 remains the
separate additive public-props hook with canonical/protocol collision rules.

### Reduce PublicRendering to project policy

For example, configure:

```php
return [
    'rootView' => 'frontend::app',
    'publicPagePolicy' => [App\Http\PublicRendering::class, 'allowsPage'],
    'indexingPolicy' => [App\Http\PublicRendering::class, 'allowsIndexing'],
];
```

The project's policy can now contain only its section selection and environment:

```php
public static function allowsPage(Request $request, Entry $entry): bool
{
    return isset(PageData::COMPONENTS[$entry->getSection()?->handle]);
}

public static function allowsIndexing(Request $request): bool
{
    return app()->isProduction();
}
```

`publicPagePolicy` is optional: `null` adds no restriction to built-in eligibility.
A configured callback must return strict `true`; a non-callable value or truthy
string denies eligibility. It runs only after all built-in checks pass, so even
an always-true callback cannot allow private, preview, missing-entry or error
contexts. Do not call `isPublicGuest()` recursively from this callback. Keep both
policies pure; eligibility may be evaluated more than once. A project can further
restrict allowed site handles in either policy without storing request state.
`indexingPolicy` still requires a callable returning strict `true`, defaults to
deny and cannot override Craft's `disallowRobots`.

The simplified production-only indexing callback above is for the shared renderer
and `HandleEntryRequests`. Legacy integrations using only `HandleInertiaRequests`
still need to establish full public eligibility in their own indexing policy.

### Preserve error responses

The original exception response remains project-owned. Retain its selection
rules and return it if CMS error projection fails. An illustrative controller
method, called only for selected HTML/Inertia site errors, is:

```php
public function error(Request $request, Response $original): Response
{
    try {
        $headers = [];
        foreach (['Retry-After', 'Refresh'] as $name) {
            if ($original->headers->has($name)) {
                $headers[$name] = $original->headers->all($name);
            }
        }

        return app(PublicRenderer::class)->render(
            $request,
            'error',
            function (string $robots) use ($original): array {
                $entry = Entry::find()->section('notfound')->one();
                $props = (new PageData())->props($entry, $robots);
                $props['error'] = [
                    'statusCode' => $original->getStatusCode(),
                    'statusMessage' => $original->getStatusCode() === 404
                        ? 'Page not found' : 'Something went wrong',
                ];

                return $props;
            },
            statusCode: $original->getStatusCode(),
            headers: $headers,
        );
    } catch (\Throwable $exception) {
        report($exception);

        return $original;
    }
}
```

Error content is deliberately queried inside the callback and is not supplied as
the request's matched entry. No error response can become eligible for SSR or
indexing, even if that content entry is live. The supplied status is set on the
page response before official adapter post-processing. An outdated asset version
can therefore replace a 404/503 page with its own 409 and `X-Inertia-Location`;
the renderer never resets that final status. Selected original error headers fill
missing final response headers, including after a version conflict. Adapter and
privacy headers take precedence. Pass only relevant error headers, rather than
stale content length/type, protocol or cache fields from the original response.

### Compatibility and verification limits for issue #19

Source compatibility was checked against the consumer at
[`3501090e`](https://github.com/robuust/robuust-cms/tree/3501090eda683f9c445c6698581f1b77e09c113e),
including its controller, policy, exception fallback and exact lockfiles
(inspected 2026-11-12):

| Package | Consumer lock | Verification |
| --- | --- | --- |
| Craft core | `6.0.0-beta.1` (`05ce9b81`) | Matching installed Playground source inspected for matched elements, site, entry status, token and flash APIs. |
| Craft Yii adapter | `6.0.0-beta.1` (`c2038019`) | Consumer lock confirmed; no compatibility changes or runtime acceptance claimed. |
| CKEditor | `6.0.0-beta.1` (`44208e17`) | Consumer lock confirmed; field serialization is unchanged. |
| Laravel | `13.42.0` (`f5c01f6b`) | Matching installed source for container/facade and response contracts. |
| Inertia Laravel | `3.7.0` (`9d3f9060`) | Matching installed adapter source inspected for factory/gateway APIs, middleware, flash, partial props and version-conflict ordering. |
| Inertia Vue | `3.11.0` | Consumer Yarn lock confirmed; no frontend change. |
| craft-inertia | `0.1.0-beta.1` (`dae6ab04`) | Consumer baseline; this change adds the shared renderer without altering native routing or default prop keys. |

Composer validation (`composer validate --strict`), all package PHP syntax checks
(`php -l`, PHP `8.5.12`) and whitespace validation (`git diff --check`) passed.
This is source inspection, not runtime acceptance. No tests were written or run
for this implementation. Consumer verification remains open for HTML/JSON and
partial requests, live/authorized preview and multisite requests, auth/token/form
exclusions, policy attempts to broaden eligibility, sequential public/private
responses (including identical page data), failure/nested-render restoration,
SSR/hydration and host callbacks, final headers after Craft's outer middleware,
404/503 headers, error-projection fallback and version conflicts. These require the consuming
project's own locked installation and separately authorized test work. Package
syntax and Composer validation cannot establish these user-facing outcomes.
The local Playground's PHP dependency sources match core/Laravel/Inertia, but its
plugin lock is a historical `0.1.0` path reference; it is not evidence of the
consumer migration being installed. No consumer source or lockfile is changed.

The remaining sections describe the Playground's earlier manual SSR, errors,
indexing and data-cache integration. They remain context for legacy integrations;
the shared renderer replaces its copied rendering boundary, not its content
queries or business policy.

## Explicit project SSR policy

The consuming application owns its Vite client/SSR builds and the official
Inertia SSR gateway. The plugin neither replaces that gateway nor registers a
global SSR callback. The Playground adds `public.ssr` route metadata only to its
published content destinations and overview. Its existing `disableSsr()` callback
allows those routes only for safe guest requests without native token/preview
state, preview elements or session form errors/messages. Authenticated content,
private file/form pages and authorized previews keep client rendering. Native
`craftUser()`, `getHadToken()`, `isPreview()`, `MatchedElement` and site-token state
supply the decision; no STEP-05 adapters or props are required.

A route flag is an explicit project promise that its props are public. It does
not sanitize a projection or authorize content. Keep publication and permissions
with Craft, project props explicit, and sensitive/challenge responses outside
the flag. A failed SSR request falls back through the official gateway. The
project logs only its failure type, never the page, exception text or stack.
Control panel SSR and the host application's existing policy stay separate.

Build both artifacts with the project's existing `yarn build`, then run its
`php artisan inertia:start-ssr` process. The SSR process uses the same page
resolver and title formatter as hydration. Do not derive a client application
name from the already-rendered document title. The SSR process must be private
to the app; deploy/process supervision is project-owned and unverified here.

## Project metadata

Playground content controllers add an explicit `seo` projection: `title`,
`description`, `canonical` and `alternates`. Its `PublicHead.vue` uses official
Inertia `Head` keys so navigation replaces metadata. Canonical URLs come from
`Entry::getUrl()` or native current-site overview URLs, with query/preview
parameters removed. Alternate URLs reuse live canonical-ID/site queries and
omit unavailable translations. There is no parallel URL or content registry.
Metadata fields and content choices belong to this example, not the package.

The public Blade root contains one Inertia head output and no competing static
title. Guest SSR supplies meaningful body content and head tags in initial HTML;
client rendering owns them after navigation or an SSR outage. STEP-06B adds the
bounded indexing and structured-data policy below. No third-party SEO integration
is advertised.

## Project indexing and structured data

The package's `indexingPolicy` defaults to `null` (deny). A project may supply a
`callable(Request): bool`, for example:

```php
return [
    'rootView' => 'frontend::app',
    'indexingPolicy' => [App\Http\PublicRendering::class, 'allowsIndexing'],
];
```

The callback must establish safe published guest eligibility from server-owned
route, native token/preview, authentication and session state. It must be pure
and safe to evaluate before the controller runs. A truthy string or a boolean
config value does not opt in: only a callable returning `true` permits indexing.
The Playground reuses `PublicRendering::allowsSsr()` and an explicit current-site
handle allowlist (`site-content.indexableSites`, empty by default). This keeps
unselected form/private routes, authenticated content, drafts/previews, native
route tokens even after their query key is consumed, native site tokens and
session form errors/messages non-indexable. A query-free URL or visitor marker
is never sufficient proof of public eligibility. Projects own their actual
crawler policy and selected content; no real site is enabled here.

`HandleInertiaRequests::robots(Request)` supplies the same decision for the
project's `seo.robots` metadata. Native `disallowRobots` overrides an opt-in.
Craft may spell the equivalent deny header `none`; the rendered meta uses
`noindex, nofollow`. Only final 200 page responses may use the allowed directive;
redirects, version conflicts and error/maintenance responses retain deny headers.
The middleware captures the decision before Inertia consumes flashdata, avoiding
a more permissive header after rendering. The project also inspects the official
current SSR page's flash field when deciding whether it may reach the SSR gateway.
Exception calls to `protectResponse()` retain its deny default. Apply this public
middleware only to page routes; native CP/actions/assets/API endpoints keep their
own handlers and policy.

The project extends its explicit `seo` projection with nullable `structuredData`:
a JSON string produced by Laravel `Js::encode()` from the same canonical URL,
title, description and site language. It is a small `WebPage` example; schema
choice and facts belong to the project, and no package fields are required.
Private/authorized preview or form-state responses omit the data. Public data
may exist on a deliberately non-indexable public page; the indexing allowlist
and structured-data projection serve different purposes.

`PublicHead.vue` uses a native script vnode inside official Inertia `Head`, with
`head-key="structured-data"` and `type="application/ld+json"`. Its text child is
already safe JSON; avoid `Js::from()` (a JavaScript expression), unencoded content
or a second client serializer. `Js::encode()` escapes HTML-sensitive characters,
including closing script sequences, while retaining parseable JSON. Head owns
SSR output, hydration, replacement/removal and history restoration. One keyed
robots meta follows the same page projection. Error pages have their own keyed
deny meta. No parallel head store, SEO dependency, robots.txt replacement or
production crawler setting is introduced. See [STEP-06B evidence](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP06B.md).

## Errors, maintenance and native isolation

The project composes its existing `Inertia::handleExceptionsUsing()` callback
with official `ExceptionResponse::usingMiddleware()` and `toResponse()`. It
selects safe HTML/Inertia page requests on explicit public middleware routes or
Craft's native site fallback. CP, actions, native JSON, assets and unrelated
routes keep their owners. Existing redirects are left intact. Public 404 pages
render `Site/Error`; 403/500/503 use that project component when debug is off.
No exception details or CP trigger are projected. Errors stay client rendered.
Native maintenance still decides access and produces 503; its `Retry-After`
header is retained. No replacement maintenance middleware or catch-all is added.

The public middleware's `protectResponse()` method applies the same conservative
headers to exception responses that bypass its route handler. It also retains
`Vary: X-Inertia`, including after the official asset-version response replaces
a page response. Projects with a global host Inertia middleware should preserve
intentional host shares but let the public adapter alone negotiate opted-in
routes, otherwise an outer adapter can replace the response a second time.

## Cache and asset versions

Every opted-in HTML and page JSON response remains `private, no-store`, including
authenticated, preview, errors and SSR fallback. `Vary: X-Inertia` distinguishes
representations but grants no shared-cache permission. STEP-06C adds data caching
below this response boundary; it introduces no HTTP output cache or dependency
watcher.

The existing plugin `version()` uses the project's `Vite::manifestHash()`.
Official Inertia middleware detects an outdated `X-Inertia-Version` and returns
409 plus `X-Inertia-Location`; the client then requests a full document with the
new build. The local fixture changes only manifest whitespace to demonstrate
negotiation without breaking asset references. This is protocol evidence, not a
production deployment or public-cache acceptance claim.

## Native public content-data caching (STEP-06C)

The [PublicContentCache project example](examples/PublicContentCache.php) composes
Craft's `ElementCaches` collector with `DependencyCache`. It is an application
convention for the Playground's article projections, not a new plugin API or a
universal cache wrapper. Copy/adapt it into `App\Http` alongside the project's
`PublicRendering` policy above. The package registers no cache middleware,
listeners, store, schema or dependency. Use Craft's installed dependency-aware
default store; the local evidence uses its file store across real requests.

Keep `'cacheEnabled' => false` in `config/site-content.php` until the selected
projections are deliberately public. Only article overview/detail routes carry
`metadata(['public.ssr' => true, 'public.contentCache' => true])`. Both flags and
the strict boolean config opt-in are required. The existing guest eligibility
excludes CP/actions, authenticated visitors, native consumed route/site tokens,
preview elements/markers/headers and session errors/messages/Inertia flash. This
cache also excludes old form input. A session that contains only normal guest
framework bookkeeping is not itself personalized content. Projects with any
other personalization must extend their owning eligibility policy first.

The detail projection runs only after native `CurrentElement` resolution and
section checks. Collect the matched element explicitly because routing hydrated
it before cache collection began:

```php
$data = PublicContentCache::remember(request(), ['article', $entry->getCanonicalId()], function () use ($entry): array {
    ElementCaches::collectCacheInfoForElement($entry);
    $this->loadContent($entry, 'articles');

    return [
        ...$this->articleSummary($entry),
        'body' => $this->textField($entry, config('site-content.articles.bodyField')),
        ...$this->content($entry, 'articles'),
    ];
});

return Inertia::render('Site/Articles/Show', [
    ...$this->pageProps($entry), // Always current request policy and SEO.
    'entry' => $data,
    'isPreview' => $entry->previewing,
]);
```

The overview normalizes `q`, `topic`, `sort` and positive page input before
lookup. Its key inputs are `['articles', $filters, $page, 20]`. Inside the
collector the existing native queries resolve topics, search/relations, count,
sort and bounded offset/limit; the value contains only projected entries/topics,
total/current page and section existence. Reconstruct the paginator and its
query links outside the cache using the native current-site URL. SEO, canonical
and alternate links, robots, JSON-LD, globals, preview state, flash, HTTP status,
SSR eligibility and asset-version negotiation remain outside both data values.
HTML and page JSON therefore consume the same data without reusing policy.

Keys contain native site ID/UID/base URL, normalized flow inputs, article/taxonomy
schema config and a projection version. The example's `v1` must change when
projection code, transforms or other output-affecting deployment inputs change;
non-Craft data needs its own evidenced dependency or must stay uncached.
Indexing settings are deliberately outside this key so a warmed public value
cannot freeze yesterday's crawler policy. No raw request URL, session, user,
query object, full element, response or head projection is stored.

Native element queries collect their tags even for empty results, retaining the
dependency when a missing filter later becomes available or new content is
published. Explicitly call `collectCacheInfoForElement()` for every projected
related element, including eager-loaded assets and recursively projected Matrix
children: hydration may have happened outside the current collector. Relation
queries and native owner invalidation also retain membership dependencies.
Saves, publish/unpublish and delete use native element APIs, which invalidate
the collected dependency. The example adds no manual purge or tag strings.

The TTL is at most 300 seconds and no later than the collector's nearest expiry.
On a miss, two native current-site queries also find the earliest enabled pending
publication and live expiry across canonical entries, including off-page and
currently invisible content. The project registers those dates with
`ElementCaches::setCacheExpiryDate()`. On the fixed CMS source, live/pending
queries compare against `now()->endOfMinute()`; the data TTL therefore ends at
the **start of the publication/expiry minute**, preserving native visibility
without waiting for a save, queue job or manual purge. A zero remaining TTL is
not stored. The collector is stopped in `finally`, including projection errors.

These broad scheduling queries intentionally collect broad native Entry tags.
Any canonical entry save can invalidate multiple flows/sites; this is safe
conservative invalidation, not a cache-granularity or performance claim. The
evidence covers the normal dynamic native statuses, not static-status cron
operation, arbitrary third-party fields, distributed stores, concurrent writes
or nested output/template caches. Never wrap the entire response or assume
element tags invalidate arbitrary config, remote data or a second cache layer.
Recheck these APIs and minute semantics on Craft upgrades.

`ElementQuery::cache()/noCache()` is the native alternative for query-result
caching. This example keeps queries uncached and caches only the final public
arrays with the native collector, avoiding a second cache lifetime. It needs no
custom invalidation watcher or shared-cache response policy. Full HTTP/CDN/browser
caching and account-dependent policies remain deferred.

Pinned primary sources: [ElementCaches](https://github.com/craftcms/cms/blob/0506c32b44efbe3e7f5fded46a7c1d39de699d5b/src/Element/ElementCaches.php),
[query tag collection](https://github.com/craftcms/cms/blob/0506c32b44efbe3e7f5fded46a7c1d39de699d5b/src/Element/Queries/Concerns/CollectsCacheTags.php),
[Entry status queries](https://github.com/craftcms/cms/blob/0506c32b44efbe3e7f5fded46a7c1d39de699d5b/src/Element/Queries/EntryQuery.php)
and [Craft's dependency-aware store](https://github.com/craftcms/laravel-dependency-aware-cache).

Primary APIs: [official SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering),
[Head](https://inertiajs.com/docs/v3/the-basics/title-and-meta),
[error handling](https://inertiajs.com/docs/v3/advanced/error-handling), and
[asset versions](https://inertiajs.com/docs/v3/advanced/asset-versioning).
The exact tested Craft source/assets identity is recorded in the slice report.
