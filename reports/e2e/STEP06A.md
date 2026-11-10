# STEP-06A: independent public rendering

This slice delivers guest public SSR, project-owned metadata, public error and
maintenance responses, and official asset-version negotiation. The human
explicitly authorized this independent subset on 2026-11-10 while STEP-05
remains open. It does not accept STEP-05, its preserved native failures, or full
AC-06. Public caching/invalidation, authenticated account lifecycle, indexability
and structured-data policy remain deferred. All opted-in responses retain
private/no-store and noindex. [Rendering contracts](../../docs/RENDERING.md)
describe the integration.

## Observable behavior

- Explicit published guest content routes render body content, one title,
  description, native canonical URLs and available translation links in initial
  HTML. JavaScript-disabled visits see content. Hydration and Inertia navigation
  update head state without duplicate content or hydration errors.
- Native authenticated sessions, authorized previews, token-bearing requests and
  session form failures remain outside this new public SSR policy. An authorized
  unpublished preview stays private and client rendered; its ordinary public URL
  remains unavailable. A separate guest receives public SSR afterward.
- The project composes its existing official Inertia exception callback with
  `ExceptionResponse`, its public middleware and `Site/Error`. Public 404/500/503
  preserve status in HTML and page JSON. Inertia error navigation removes stale
  content metadata. Native maintenance supplies 503 and `Retry-After`. Existing
  redirects retain their destinations/status. CP, native actions, JSON, assets,
  debug responses and unrelated routes retain their existing owners.
- An SSR outage falls back to private client rendering. SSR failure logs contain
  only the failure type, never page props, error text or stack.
- A changed project Vite manifest triggers the official 409 location response
  and a full document reload. `private, no-store` and `Vary: X-Inertia` survive
  response replacement. The host middleware preserves intentional shares while
  leaving public protocol negotiation to the public adapter.

The package adds only reusable `protectResponse()` behavior. Routes, policy,
content projections, Vue components, root view and builds remain project-owned.
No package demo schema, account adapter, dependency, output cache or parallel
error/maintenance engine is added.

## Verification and provenance

| Check | Result |
| --- | --- |
| Native identity | Exact official CMS `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` and assets `17973259d5727a1e50a1fc85dd90566b889407af`; installed trees, native published assets and CP manifest match accepted STEP-04. `6.x-dev as 6.0.0-alpha.20` is an alias, not released alpha.20. |
| Runtime/build | PHP `8.5.12`, Node `24.15.0`, Laravel `13.40.0`, Inertia Laravel `3.7.0`, Vue adapter `3.11.0`, Vue `3.5.45`. Fresh client and SSR builds pass with existing dependencies. |
| PHP | 45 tests / 245 assertions pass together: new rendering/privacy/fallback/errors plus public/form/content and existing host SSR/error contracts. Explicit `--no-coverage`; no PHP coverage driver or coverage claim. |
| Vue | All 47 tests pass. Required full-suite 100% line coverage passes; new `PublicHead.vue` and `Site/Error.vue` each have 100% statements, branches, functions and lines. |
| Public rendering browser | All 12 cases pass together in `browser-public-final/`, including real native preview/authenticated exclusions, maintenance, outage and version reload. Zero unhandled browser errors or hydration mismatch messages. |
| Predecessor browser | All 52 unique routing/content/site cases pass across the 37 STEP-01 cases in `browser-accepted/` and the 15 STEP-02/03 cases in `browser-corrected/`. |
| Forms/files browser | All 11 cases pass together in `browser-forms-final/`, including invalidated sessions, native CSRF, private bytes and the native 60/minute initiation limit. |
| Static | TypeScript, Prettier, scoped ESLint/PHP CS Fixer, PHP syntax, Composer strict validation and whitespace checks pass. |
| Independent review | Source review PASS, zero material standards/spec findings; final source-bound evidence audit is recorded externally in `review.md`. |

These are **75 unique passing browser cases across separate runs**, not one green
75-case execution. No new complete ordinary-editor/autosave acceptance or
STEP-05 account regression claim is made. PHP and browser shared-schema checks
ran sequentially.

Evidence root:
`/Users/joostwaaijer/.codex/agent-team/runs/craft-inertia-execution-20261109/step06a/`.
`accepted-prepare/` holds exact root locks/manifests, full dependency/native asset
hashes, source input and build logs. `php-accepted.log`, `vue-accepted.log`,
`vue-coverage/` and the browser directories above bind results to the final
source. All 37 changed fixture input hashes match the fresh preparation.

Initial failures remain preserved. The first PHP checks exposed a host outer
adapter dropping no-store on asset-version replacement; the host now lets the
public adapter own negotiation. Broader host checks needed isolated SSR
fallback configuration/rate-limit state and disabled outbound SSR in tests
concerned only with error statuses. The first browser run's production debug
setting hid native error messages asserted by six existing routing cases; it is
now restricted to the isolated maintenance check. The older public CSR assertion
now requires the intended guest SSR. A rapid alternate-schema config rewrite
returned 404 once, then passed in isolation; the corrected mutable-config run
uses the local PHP server with OPcache disabled and all 15 content/site cases
pass. One new test incorrectly iterated keyed site fixtures as an array; its
corrected assertions pass. No native assertion, protection or account failure
was suppressed or marked expected.

## Local integration and preservation

Plugin branch: `feature/public-rendering`, based on merged STEP-04
`500409e051d6c398fe626b95db9eef3c872a7d83`, targeting
`feature/inertia-proof-of-concept`. One plugin-only PR is handed to the
coordinator; this task does not merge or assign further work. This personal
repository has no PR template, CI workflow or meaningful changelog. No team,
label, changelog release or auto-merge requirement is invented.

The original Playground remains untouched on `feature/native-inertia-pages` at
`f7dd95da555f704d49aa590f7fa60bf7d24f1731`. Its exact 38 STEP-05 local files,
plugin's exact 15 preserved files and stash
`a13bfe765f78af9fce896dc9032c92082f7160b6` are reverified. The stash is not applied,
popped or dropped. STEP-05 source/reports are excluded from this PR.

Only the disposable local source
`step06a/playground-source` was edited. It remains uncommitted at that same
`f7dd95d` HEAD. `playground.patch` is the complete 37-file aggregate;
`playground-step06a-incremental.patch` contains 21 STEP-06A files.
`playground-snapshot.json` gives every file hash and patch hashes;
`before-playground/` and `after-playground/` provide per-file merge inputs.
The incremental patch passes `git apply --check` against the saved STEP-04
fixture baseline. The coordinator must integrate it locally against those
before/after copies, resolving overlaps with preserved STEP-05 work rather than
blindly replacing the original checkout. No Playground commit, push or PR is
made. `plugin-snapshot.json` records the plugin fingerprint separately.

Fresh schema `6.0.0.35` exists only in the isolated runtime; copied source YAML
`6.0.0.22` remains as evidence. No original database, YAML, env, production data,
real mail or third-party service was changed. Local builds and tests establish
neither an original-app upgrade nor a production deployment. Owned test services
are stopped at handoff; the isolated database volume and evidence are retained.
