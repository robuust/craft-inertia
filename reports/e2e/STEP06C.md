# STEP-06C — Native public content-data caching

## Delivered boundary

This bounded REQ-12 slice adds an opt-in project convention for article detail
and filtered/paginated overview data. The checked
[PublicContentCache example](../../docs/examples/PublicContentCache.php) uses
native Craft `ElementCaches` collection and `DependencyCache` storage. Native
APIs already own dependency tags and invalidation; the plugin runtime, public
middleware, official Inertia adapter and package dependencies are unchanged.
The consuming-project integration remains local and uncommitted.

Caching defaults off. A strict boolean project setting, explicit route metadata
and the existing native published-guest eligibility are required. Authenticated,
preview/draft, consumed route-token, site-token, form-error/message/flash and old
input requests bypass it. Native current element and site resolution still run
for every detail request. No full element, user, query, response, session, SEO or
protocol state is cached. Only explicit public arrays enter the store.

Keys distinguish native site ID/UID/base URL, normalized search/topic/sort/page,
flow, page size, article/taxonomy schema config and projection version. HTML and
Inertia JSON share data while rebuilding SEO/robots/JSON-LD, alternate and
pagination links, globals, preview state, session, SSR, errors and asset versions.
Every public HTML/page JSON response retains private/no-store and Vary: X-Inertia.

Native saves, publish/unpublish, deletes, related entry/asset updates, taxonomy
publication and nested Matrix saves refresh the data. Explicit collection covers
the already-matched entry and every projected eager-loaded related element.
Query tags retain empty-result dependencies. Two broad native current-site
queries register the earliest pending publication and live expiry, including
off-page and invisible entries. The TTL is at most 300 seconds and ends no later
than those native boundaries. This fixed Craft source compares query dates with
the end of the current minute, so the cache expires at the start of the affected
minute. No manual purge, custom tag strings, watcher, registry or scheduled
invalidation job is added. Projection errors close the collector without storing.

These broad native Entry dependencies favor correctness over fine-grained cache
reuse. A canonical Entry save can invalidate several flows/sites. No performance,
distributed-cache, concurrent-write, static-status cron, nested output-cache,
arbitrary third-party-field or production claim follows from this local slice.
Projects must bump the projection version for code/transform/deployment inputs
and keep unrelated or non-Craft dependencies outside this example until covered.
See [the integration contract](../../docs/RENDERING.md#native-public-content-data-caching-step-06c).

Full HTTP response/CDN/browser caching, private/account lifecycle, the native
STEP-05 defects, full AC-06, third-party compatibility and package release remain
open. No native/vendor fix, dependency upgrade, upstream report, original DB/YAML/
env change, real mail or deployment is included.

## Provenance and checks

- Fixed official CMS `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` and cms-assets
  `17973259d5727a1e50a1fc85dd90566b889407af`, using the existing isolated preparation
  script and `tests/e2e/official-craft.json`.
- The `6.x-dev as 6.0.0-alpha.20` alias is not released alpha.20 source.
- PHP 8.5.12, Node 24.15.0, Laravel 13.40.0, Inertia Laravel 3.7.0,
  Inertia Vue 3.11.0 and Vue 3.5.45. No dependency baseline upgrade.
- The source copy retains the accepted 6B dependency/config basis. Only the
  isolated runtime gets native schema-35 installation, fixture sites/routes,
  plugin path metadata, test env and generated license/project config.
- Final byte checks match all 2,821 CMS, 8,097 cms-assets and 8,093 published
  native asset files against preparation hashes, with no extra files. Source
  and dist references match the fixed pins. The source lock remains byte-identical
  to the accepted 6B fixture.

Scoped tests remain explicitly authorized for this objective. Final evidence:

| Check | Result and limits |
| --- | --- |
| PHP cache contracts | 26 tests, 184 assertions passed in the focused run |
| Final PHP cache + affected public/content regressions | 102 tests, 555 assertions passed, `--no-coverage` |
| Final browser caching/indexing/rendering | 27 passed: 6 STEP-06C, 9 STEP-06B, 12 STEP-06A |
| Existing route/content/multisite browser regressions | All 52 STEP-01/02/03 cases passed in the first development run |
| Client and SSR builds | Passed during fresh preparation; frontend sources remain unchanged from accepted 6B |
| TypeScript and Prettier | Passed |
| Scoped PHP CS Fixer | 0 changes required on final 8 project/example/harness files |
| PHP syntax, Composer metadata, Git whitespace | Passed |
| Aggregate and incremental local patches | Apply cleanly and reconstruct every source file byte-for-byte |
| Independent review | Pending coordinator review of this stable artifact; no new agents spawned |

The 52 predecessor and 27 final browser cases are separate runs, not one green
aggregate. Browser checks observe native cache read/write events on separate
real requests through runtime-only diagnostic headers, then assert actual
content and policy. A miss/write followed by a hit without a write demonstrates
reuse; native publication/save/delete produces a fresh write and changed visible
content. No native cache is faked or manually purged. Raw native CacheHit events
may precede a dependency rejection, so invalidation assertions also require a
new write and updated public data.

The PHP tests use real native saves/deletes and the installed isolated database,
with transaction rollback. Laravel's test clock moves immediately before and
onto the native minute boundaries, without intervening writes. They cover an
empty pending query becoming visible, expiry, an expiring relation disappearing,
taxonomy publication, asset/related/nested updates, schema/filter/page variation,
strict default opt-in, sensitive-state bypass and collector cleanup on failure.
PHP response tests clear scoped container instances between requests to model
the native per-request element lifecycle; real browser requests verify that
boundary independently.

PHP coverage percentages are unavailable because Xdebug/PCOV are absent. The
Playground's full PHP CI target is 100%; this slice does not establish that
full-suite threshold. Vue code and its existing 100%-line threshold are unchanged,
so the accepted 6B Vue evidence is reused instead of rerunning unaffected tests.
The unaccepted STEP-05 account suite remains excluded; its native failures are
not waived. There is no plugin CI workflow or new coverage exclusion/threshold.

Development failures are retained externally. The initial browser run had 57
passes and one new cold-cache assertion failure. A second run had 25 passes and
two failures, exposing reused warm fixture state and rapid config changes under
the built-in server's still-enabled OPcache. The cold-cache test now creates and
cleans up its own entry through native APIs. The final server disables both
`opcache.enable` and `opcache.enable_cli`; all 27 final cases pass without sleeps,
skips, relaxed assertions or product policy changes. A temporary eligibility
diagnostic was removed. An initial preparation accidentally selected the default
source path and was interrupted; the correct run explicitly selects the new
6C source. All original source hashes remain intact.

## Local delivery and preservation

Plugin branch `feature/public-content-cache` starts at merged PR #6's exact
`dc41032c5de4471d1a304028ce07963d4fc6800d`, targeting
`feature/inertia-proof-of-concept`. One fresh plugin PR is authorized; the
coordinator owns independent review and merge. The personal plugin repository
has no PR template, CI workflow or meaningful changelog. No Robuust metadata,
auto-merge, repository setting or release is introduced.

The original Playground remains on `feature/native-inertia-pages` at
`f7dd95da555f704d49aa590f7fa60bf7d24f1731`; all 48 saved changed-file hashes match.
The accepted 6B source is read-only and all 38 hashes match. Plugin STEP-05 stash
`20dacafb9ae1729d1ae2fe7b5f6bfefbd9493f17` retains its exact 15 files; older stash
`a13bfe765f78af9fce896dc9032c92082f7160b6` and protected
`feature/native-accounts-access` at `cca192be410789c260946e99097729c168552d6b`
remain intact. No stash is applied, popped or dropped.

Only `step06c/playground-source` is edited, detached at the same `f7dd95d` base,
with no commits, pushes or PRs. It holds the 40-file independent aggregate and a
five-file incremental change: SiteController, PublicContentCache, site-content
config, public route metadata and PublicContentCacheTest. The coordinator must
merge the incremental inputs with preserved native-account work; replacing the
original checkout with this public-only fixture would discard unrelated work.

Evidence root:
`/Users/joostwaaijer/.codex/agent-team/runs/craft-inertia-execution-20261109/step06c/`.
`playground-snapshot.json` contains all 40 file hashes, both patch hashes and
the five-file incremental list. `before-playground/` and `after-playground/`
retain exact integration inputs; native Git modes are preserved.

- Aggregate `playground.patch` SHA-256:
  `ac51d9daff554aa2fffea01ff090ad4e2dfb57c2cdb6a9e0d8e3c67482a29975`.
- Incremental `playground-step06c-incremental.patch` SHA-256:
  `e4b2eb0d24cc5d3f7267e96a66c39b17020c2e9c999c78f845c1c5263211aaa8`.

The runtime source/overlay hashes, native byte identity, preservation checks,
plugin diff/fingerprint, self-review and live publication identity are separate
handoff artifacts. PHP/SSR/PostgreSQL services are stopped at handoff, retaining
the isolated volume and evidence. No next slice is assigned here.
