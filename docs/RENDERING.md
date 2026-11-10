# Public rendering and response policies

STEP-06A and STEP-06B are human-authorized independent parts of STEP-06. STEP-05 remains
open, including its native account blockers. This slice adds guest public SSR,
project metadata, public errors/maintenance and asset-version evidence. It does
not deliver account behavior, public caches or invalidation; full AC-06 remains
incomplete. See [verification and delivery](../reports/e2e/STEP06A.md).

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
production crawler setting is introduced. See [STEP-06B evidence](../reports/e2e/STEP06B.md).

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
representations but grants no shared-cache permission. No output cache, cache
keys, generation helpers, dependency watchers or invalidation are introduced.

The existing plugin `version()` uses the project's `Vite::manifestHash()`.
Official Inertia middleware detects an outdated `X-Inertia-Version` and returns
409 plus `X-Inertia-Location`; the client then requests a full document with the
new build. The local fixture changes only manifest whitespace to demonstrate
negotiation without breaking asset references. This is protocol evidence, not a
production deployment or public-cache acceptance claim.

Primary APIs: [official SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering),
[Head](https://inertiajs.com/docs/v3/the-basics/title-and-meta),
[error handling](https://inertiajs.com/docs/v3/advanced/error-handling), and
[asset versions](https://inertiajs.com/docs/v3/advanced/asset-versioning).
The exact tested Craft source/assets identity is recorded in the slice report.
