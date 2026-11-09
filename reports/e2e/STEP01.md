# STEP-01 native URLs and project pages

Verified locally on 2026-11-09 against Craft 6.0.0-alpha.20, Laravel 13.40.0,
inertia-laravel 3.7.0, Vue 3.5.45, Inertia Vue 3.11.0, PHP 8.5.12, Node 24.15.0
and Chrome via the existing Playwright harness. This is local integration
acceptance, not deployment or complete preview acceptance.

## Delivered contract

The package exposes opt-in public Inertia middleware and a configurable project
root view. It uses the project's Laravel Vite manifest and preserves intentional
project shared props. The numeric demo routes, fixed article controller, extra
preview target, Vue app, layout, assets and package frontend build are removed.
Projects own their routes, controllers, explicit props and Vue presentation.

The local Playground example has a native homepage single, article overview,
named article destination and registered controller destination for projects.
These use Craft's CurrentElement/ElementRoute resolution and canonical URLs.
Articles and projects have distinct pages and field projections. An alternate
journal/teaser/copy and caseStudies/city configuration works without package edits.
Craft dynamic view routes retain precedence for identical route signatures.
Public HTML and page JSON retain the conservative private/no-store policy.

## Evidence

| Check | Result | Coverage and source |
| --- | --- | --- |
| STEP-01 Chrome project | 37 passed; 0 failed/skipped/flaky | `tests/e2e/plugin.spec.ts`; ignored `reports/e2e/step01/junit.xml` and `results.json` |
| PHP feature checks | 17 tests, 75 assertions passed | Playground PublicInertiaTest, ServerSideRenderingTest, AuthenticationTest; ignored `reports/e2e/step01/phpunit.xml` |
| Vue suite | 24 tests passed across 6 files | Playground existing Vitest suite plus project page cases; 100% component line coverage and 100% branch coverage for new pages/layout |
| Build | Client and SSR builds passed | Playground `yarn build`; ignored `reports/e2e/step01/logs/host-build.log` |
| Plugin static checks | Passed | Composer strict validation, TypeScript test tooling, Prettier, PHP syntax/CS Fixer and whitespace checks |
| Playground source checks | Passed | Standard ESLint command, scoped PHP CS Fixer and whitespace checks |
| Independent review | PASS; zero material findings | Read-only reviewer `/root/review`, separate from implementation and E2E author |
| Separate editor-preview project | 1 passed, 1 failed | Named-draft popup passed. Live Preview functional refresh/public separation passed, then the browser-error assertion failed with Craft's WeakMap exception |
| Native Twig control | Same WeakMap exception reproduced | HTTP 200 and autosave refresh; no package or project frontend bundle requests in the preview control |

The 37 scoped browser cases cover native homepage, canonical links, direct/JSON
agreement, two field schemas, an alternate configuration, client navigation and
Back, unavailable entries, escaping, empty/missing fields/sections, dynamic
GET/HEAD collisions, token/privacy boundaries and preview headers on both
representations. Native registered named/controller destinations retain route
middleware, and six URI/domain/method mismatch cases cannot dispatch their
destinations. CP, assets, actions and the GraphQL authorization response retain
their own handling; existing host SSR remains separate from the public examples.
These checks do not establish successful authenticated GraphQL queries or
arbitrary third-party endpoint compatibility.

PHP ran with `INERTIA_SSR_THROW_ON_ERROR=false CACHE_STORE=array` to match the
host fallback tests rather than the browser environment's deliberate SSR
throw-on-error/file-cache settings. No PHP coverage driver is installed, so PHP
tests ran with `--no-coverage`; no PHP coverage percentage is claimed. Playground
Composer strict validation retains its pre-existing missing-license warning.
An additional ESLint invocation outside the standard source lint command found
pre-existing config/test-setup global/style issues; standard source lint passes.

The original [26 passed / 1 failed report](REPORT.md) and its artifacts remain
unchanged. The new editor failure is not waived, suppressed or counted as a
pass. **The complete browser suite is not green.** Complete editor, revisions and
multisite acceptance remain STEP-03; no upstream fix is included in this slice.

## Local Playground delivery boundary

At the user's direction, all Playground changes remain local and uncommitted;
no Playground commit, push or PR was created. Its HEAD is
`f7dd95da555f704d49aa590f7fa60bf7d24f1731`. The local patch includes the controllers,
config, project pages/layout, routes, SSR selection, Composer path integration,
container mount and focused test/tooling changes (18 files).

The tested working-tree patch SHA-256 is:

```text
478345d340060f91334ea351fdcfbfb6d13c27c3ad19c3916dc6f16ddd0364fa
```

Preparation records exact package runtime and Playground file hashes. The
isolated runtime consumes this package through Composer, uses its own PostgreSQL
database, and seeds test-only sections/content. The original Playground database
was not changed or validated. Its real content schema still needs the configured
native destinations before these example pages can be used there.

Operational handoff artifacts include `playground.patch`,
`playground-snapshot.json`, `checks.json` and the independent review record under
the execution run's `step01` directory. Generated browser diagnostics, traces,
screenshots and native-preview control output remain local under the ignored
`reports/e2e/step01/`. There is no production deployment or merge claim.
