# Public rendering and response policies

STEP-06A is the human-authorized independent part of STEP-06. STEP-05 remains
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
client rendering owns them after navigation or an SSR outage. This experiment
retains `X-Robots-Tag: noindex, nofollow`; indexability and structured data policy
are deferred. No third-party SEO integration is advertised.

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
