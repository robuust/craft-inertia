# STEP-02: content and queries

Implemented on 2026-11-09 after merged STEP-01 PR #1 (`e23f8a9`). The plugin
runtime remains unchanged: schema-specific projections, queries, pages and assets
belong to the consuming project. The reusable deliverable is the documented
[content/query convention](../../docs/CONTENT.md), native fixture coverage and
integration harness.

## Delivered behavior

- Article and project pages render native Markdown, relations, nested Matrix,
  shared single content, entry taxonomy and native responsive image transforms.
- A journal/case-study configuration uses different section and field handles
  with the same package. Missing optional fields have explicit empty values;
  unknown or disabled blocks and unavailable relations are omitted.
- Search, taxonomy filters, allowlisted sorting and native 20-item pagination use
  shareable URLs. Inertia links preserve query parameters, changed filters reset
  the page, out-of-range pages clamp, and browser Back restores the received
  filters and results. Queries remain scoped to live content in the current site.
- Plain text stays escaped. Only sanitized native Markdown reaches Vue HTML
  sinks. Explicit projections exclude internal elements, users, queries and field
  layouts. No package serializer framework or content schema was introduced.

REQ-03/05 and AC-02 are covered; native asset reads support REQ-04. Rich text here
means native Markdown, globals a native single, and taxonomy native Entries.
Third-party rich-text compatibility is not claimed. Matrix types are project
allowlisted and projection depth is bounded to three levels.

## Verification

The isolated snapshot uses Craft `6.0.0-alpha.20`, Laravel `13.40.0`, PHP `8.5.12`,
Inertia Laravel `3.7.0`, Inertia Vue `3.11.0`, Vue `3.5.45` and the locked project
build. Real Playground schema and data are untouched.

| Check | Result |
| --- | --- |
| PHP content/query and public middleware integration | 14 tests, 86 assertions passed; no PHP coverage driver, `--no-coverage` |
| Vue suite | 29 tests passed; 100% lines; new content components/pages have 100% branch coverage |
| Client and SSR builds | Passed in fresh snapshot preparation |
| STEP-01 browser regression | 37 passed, preserving native routing, shells, endpoint, preview/privacy and publication behavior |
| Combined browser run | 42 passed (37 STEP-01 + 5 STEP-02), zero failures |
| Review correction | Single Vue root wrapper; fresh client/SSR builds, 29 Vue tests and 6 relevant browser checks passed |
| STEP-02 browser content/query coverage | 5 passed; both schemas, native values, sanitizing, missing fields, filters, history and pagination |
| Formatting/static checks | PHP syntax/CS Fixer, Composer strict validation, TypeScript, Prettier, project source ESLint and diff checks passed |

PHP command from the isolated snapshot:

```sh
INERTIA_SSR_THROW_ON_ERROR=false CACHE_STORE=array \
POC_FIXTURES_PATH=/absolute/path/to/craft-inertia/tests/e2e/.runtime/fixtures.json \
vendor/bin/phpunit --no-coverage tests/Feature/ContentQueryTest.php tests/Feature/PublicInertiaTest.php
```

`prepare.mjs --fresh --working-tree` copies the explicitly accepted dirty
Playground without creating a commit, records source hashes, creates a separate
PostgreSQL database and copies the harness-only PHP content test. The routing
fixture guards its class declarations so PHPUnit can bootstrap multiple apps
while registering fixture routes for each app.

The schema-switch browser check initially produced a 404 when PHP's built-in
server retained compiled config across rapid rewrites; the same check passed
alone. The deterministic browser server disables opcode caching (no test retries
or sleeps): from the isolated `public` directory run
`php -d opcache.enable=0 -S 127.0.0.1:18766 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`.
The complete content project then passed. This environment choice affects only
the disposable harness, not plugin response behavior.

The original editor-suite failure remains unchanged: one native Craft WeakMap
JavaScript error also reproduced with the Twig control. That suite was not rerun
for unchanged editor behavior, hidden or waived. The entire roadmap/editor suite
is **not green**; full editorial/multisite acceptance remains STEP-03. Public
SSR/SEO/cache policy, actions, uploads and accounts retain their later slices.

## Local delivery and evidence

All Playground changes remain local and uncommitted at
`f7dd95da555f704d49aa590f7fa60bf7d24f1731`; no Playground commit, push or PR exists.
The 18 prerequisite STEP-01 file hashes were verified before editing. Scoped
STEP-02 changes extend those files and add two content components and a Vue spec.

Exact aggregate and incremental patches, file hashes, native API notes, checks,
source copies and review/publication records are stored outside the repositories
under the execution run's `step02` directory. Browser diagnostics/JUnit and PHP
JUnit are retained locally under ignored `reports/e2e/step02/`; STEP-01 evidence
is unchanged. No production deployment or live-content acceptance is claimed.

Final Playground patch digests (including the single-root review correction):

```text
aggregate: ec086e4b6984d01fd4a5a3f6b7cf3ee368c0e04d2e7524384654b003fccdbbb7
STEP-02:   2e97e16027cbd2dc8d7f451b2d24197de723c14f57733593273cca7ee87a7290
```

The aggregate contains 21 local modified/new files; the incremental patch changes
11 files relative to the accepted STEP-01 snapshot. Ten prerequisite file hashes
remain identical. Task-owned PHP, SSR and isolated PostgreSQL processes are
stopped; the isolated database volume is retained.
