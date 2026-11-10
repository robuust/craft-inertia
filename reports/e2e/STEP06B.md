# STEP-06B — Public indexing and structured data

## Delivered boundary

This independent REQ-11 slice adds opt-in project indexing and safe JSON-LD
alongside the merged STEP-06A rendering contract. The plugin still registers no
public pages/schema and defaults to `noindex, nofollow`. An optional
`indexingPolicy(Request): bool` callback may opt explicitly selected successful
public page responses into `index, follow`; native `disallowRobots` overrides it.
The Playground example reuses its existing published-guest SSR eligibility and
an empty-by-default current-site handle allowlist. No real site or production
crawler policy is enabled.

HTTP directives and keyed robots metadata agree for HTML and Inertia navigation.
Native `none` is equivalent to the rendered deny directive. Authenticated,
unselected form/private, preview/draft, native route/site token and session
form-state responses remain non-indexable. Native consumed-token context is
checked even after the token query key has gone. Redirects, errors, maintenance,
empty responses and version conflicts keep deny headers. CP/actions/assets/API
routes retain their owners; public middleware remains page-route-only.

A project-owned WebPage example uses the existing canonical URL, title,
description and language. Laravel `Js::encode()` creates parseable JSON with
HTML-sensitive characters escaped. PublicHead renders it as the text of a
native script vnode through official Inertia Head, with one stable head key.
Private/authorized preview and form-state responses omit structured data. The
package requires no schema fields or SEO dependency. A deliberately noindex
public page may still expose its public structured data.

Indexing does not enable shared caching: all selected responses remain
private/no-store with Vary: X-Inertia. No public cache/invalidation, native account
fix, dependency upgrade, upstream report, production deployment, real mail or
third-party integration is included. STEP-05, its four native failures, full
AC-06 and release acceptance remain open. No partial STEP-05 source/report is
included in this fresh PR.

## Native and adapter provenance

- Official CMS `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` and cms-assets
  `17973259d5727a1e50a1fc85dd90566b889407af`, fixed by
  `tests/e2e/official-craft.json`.
- `6.x-dev as 6.0.0-alpha.20` is a compatibility alias, not released alpha.20
  source. No native/vendor edits, private asset build or moving resolution.
- PHP 8.5.12, Node 24.15.0, Laravel 13.40.0, Inertia Laravel 3.7.0,
  Inertia Vue 3.11.0 and Vue 3.5.45.
- The fresh `prepare.mjs --working-tree --fresh` snapshot uses only the disposable
  STEP-06B source. It retains copied source schema-22 YAML as evidence, omits it
  only from isolated installation and installs/seeds native schema 35. Original
  DB, env and YAML remain untouched. Subsequent source copies into the runtime
  are recorded and checked against final per-file input hashes.
- Final byte identity rechecks all 2,821 CMS, 8,097 cms-assets and 8,093 published
  native asset files against preparation hashes and verifies source/dist refs.
  Site fixture setup expands only isolated route config; final source keeps the
  indexable-site allowlist empty.

Primary behavior references: [Inertia Head](https://inertiajs.com/docs/v3/the-basics/title-and-meta),
[Laravel Js encoding source](https://github.com/laravel/framework/blob/v13.40.0/src/Illuminate/Support/Js.php),
and pinned native [token handling](https://github.com/craftcms/cms/blob/0506c32b44efbe3e7f5fded46a7c1d39de699d5b/src/Http/Middleware/HandleTokenRequest.php).

## Verification

Scoped tests were explicitly authorized by the human and remain authorized for
this follow-up. Final checks and material limits:

| Check | Result |
| --- | --- |
| PHP public indexing, SSR, errors, middleware and asset-version tests | 67 tests, 322 assertions passed, `--no-coverage` |
| Vue suite | 48 tests passed; 100% lines; PublicHead 100% lines/branches |
| Client and SSR builds | Passed on Node 24.15.0 |
| Project ESLint | Passed without warnings after component formatting |
| PHP CS Fixer | 0 changed files on the 5 affected project PHP files and 4 plugin/harness PHP files |
| TypeScript, Prettier, PHP syntax, Git whitespace | Passed |
| Final real-browser rendering/indexing run | 21 passed: 9 STEP-06B and 12 affected STEP-06A cases |
| Predecessor browser run | All 52 STEP-01/02/03 cases passed in the first development run |
| Independent review | Final source-bound outcome is recorded in the external handoff evidence |

These are 73 unique browser cases across separate runs, not one green aggregate.
The final run proves actual default/opt-in headers and metadata in both
representations, native disallowRobots precedence, hostile JSON-LD in initial
SSR/no-JS HTML and hydration, Inertia replacement/removal/deduplication and
Back/Forward, native token/site/preview/auth privacy, flash consumption and
native multisite identity. Affected STEP-06A checks cover errors, maintenance,
SSR fallback, native isolation and asset versions. Browser traces, screenshots,
JSON and JUnit reports are retained externally.

A real privacy defect was found during development: Inertia consumes one-time
flashdata while constructing its response. Recomputing indexing afterwards
could produce a more permissive header than the already-projected metadata.
The middleware now captures the decision before rendering. The project's SSR
callback also examines the official current SSR page's flash field, preventing
consumed flash from reaching the gateway. Dedicated PHP/browser regressions
cover this; no copied flash store or custom transport was added.

Failed development runs remain intact. The first PHP run had two incorrect
HTML-only test-helper uses on JSON responses and one real flash/header failure;
all were corrected. Browser development run 1 had 58 passes/1 failure: the
indexing fixture's opt-in replacement was a no-op after native multisite setup
serialized config using `array()` rather than `[]`. A later 19-pass/2-failure
run confirmed the same cause. The matcher now covers both native forms and
asserts the expected input exists. A separate direct-server restart initially
used the wrong working directory and produced front-controller failures; those
results are also preserved. The final direct PHP server runs from public/ with
the absolute native Laravel router and OPcache disabled for deterministic rapid
fixture config changes. No product assertion or native failure was weakened,
skipped or marked expected.

PHP coverage is unavailable because no coverage driver is installed. The tests
are response/contract evidence, not PHP percentage coverage. The preserved
combined STEP-05/06A account suite is deliberately absent from this independent
baseline; its native failures are not waived. Production hosting, crawler
behavior, original-app migration and full cache/account acceptance are unverified.

## Local integration and preservation

Plugin branch `feature/public-indexing` starts at merged STEP-06A
`cca192be410789c260946e99097729c168552d6b`, targeting
`feature/inertia-proof-of-concept`. PR #5's merged state was freshly verified.
This is one plugin-only PR; the coordinator owns merge and subsequent work.
The personal repository has no PR template, CI workflow or meaningful changelog;
no Robuust team, testable label, auto-merge, release or settings are invented.

The original Playground remains read-only on `feature/native-inertia-pages` at
`f7dd95da555f704d49aa590f7fa60bf7d24f1731`, with all 48 saved dirty-file hashes
unchanged. Plugin STEP-05 work remains in stash
`20dacafb9ae1729d1ae2fe7b5f6bfefbd9493f17` with 15 exact preserved files;
older stash `a13bfe765f78af9fce896dc9032c92082f7160b6` and protected branch
`feature/native-accounts-access` remain intact. Nothing is applied/popped/dropped.

Only disposable `step06b/playground-source` is edited, detached at the same
`f7dd95d` HEAD with no commits or pushes. It has 38 changed/new files: the accepted
37-file independent STEP-06A aggregate plus this slice's incremental changes.
The 10-file incremental patch applies cleanly against saved before inputs; the
complete aggregate applies cleanly against the exact fixture base.

Evidence root:
`/Users/joostwaaijer/.codex/agent-team/runs/craft-inertia-execution-20261109/step06b/`.
`playground-snapshot.json` gives every file hash, patch hashes and incremental
file set. `before-playground/` and `after-playground/` are exact merge inputs.
The coordinator must merge these changes locally with preserved STEP-05 work;
blind replacement of the original checkout would lose unrelated work.

- Aggregate `playground.patch` SHA-256:
  `8f806cd70a26ae45eaeb5ba668aab587712b433e08be7333f6e89225fa24924f`.
- Incremental `playground-step06b-incremental.patch` SHA-256:
  `ba454030e694c97520a21fcc18219ffd251d88230e523da73673ebedc1838f7d`.

`plugin-snapshot.json`, final runtime source/byte-identity records, preservation
verification, independent review and publication identity are separate handoff
artifacts. One new read-only reviewer brings global new-agent use to 10/10;
there are no children or correction agents. Owned PHP/SSR/PostgreSQL services
are stopped at handoff, retaining the isolated volume and evidence. Checkouts
are released to the coordinator without restoring any preserved work.
