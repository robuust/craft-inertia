# STEP-03: native preview and multisite

STEP-03 is implemented and validated against the explicitly approved official
6.x development baseline below. The released alpha.20 result remains failed and
is preserved in the historical record after this section; it was not patched,
waived or reclassified. The package transport runtime remains unchanged and
schema-free.

## Approved official 6.x baseline

| Package | Exact official source and dist reference |
| --- | --- |
| `craftcms/cms` | `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` |
| `craftcms/cms-assets` | `17973259d5727a1e50a1fc85dd90566b889407af` |

The fixed package metadata in `tests/e2e/official-craft.json` is derived from
those exact manifests. Both root packages use the development alias
`6.x-dev as 6.0.0-alpha.20`; both source/dist refs are locked. CMS requires assets
through `self.version`, so a single CMS hash or plugin-level pin is insufficient.
The CMS version constant also still says alpha.20 and cannot identify this dev
code. Installed manifests are checked byte for byte against the fixed upstream
manifests, and all published CP files are checked against Craft's native publish
map. The source/root lock and full installed/published file hashes are retained.

Necessary resolution changes are CMS/assets, Laravel AI `1.3.0`, Wayfinder
`0.1.23`, Symfony YAML `8.1.11` and HTML Sanitizer `8.1.11`. Laravel stays
`13.40.0`, Inertia Laravel `3.7.0`, Inertia Vue `3.11.0`, Vue `3.5.45` and PHP
`8.5.12`; Node `24.15.0` matches the declared project engine. Composer refused
retaining the older Symfony patches under its security policy; no policy bypass
was applied. No unrelated application dependencies were added.

This validates a fresh isolated PostgreSQL installation. The original project's
schema YAML is `6.0.0.22`; the new installer uses `6.0.0.35` and correctly rejects
the older YAML for a fresh install. Preparation preserves the copied YAML as
evidence and omits it only inside the disposable snapshot. The native installer
and guarded native fixtures create the new schema/Pro test edition. The real
Playground database and original YAML remain untouched: an existing database or
project-config upgrade is **not verified**. Verified prebuilt CP assets are also
available in the local ignored Playground public asset directory without running
its Artisan bootstrap or touching its database.

## Current native preview acceptance

The official source replaces legacy Preview with native Vue Live Preview. Tests
click the ordinary editor's real **Preview** control, and separately open the
native standalone Vue screen. Both use one editor/autosave queue and replace the
iframe after saves. Tests change title, summary and body without publishing or
manual refresh, verify independent public contexts still see published content,
and keep strict zero-browser-error assertions. All three site contexts are
covered: primary, `/nl/` and a disposable `nl.craft.test` origin. The ready iframe
retains the native canonical URL and preview-token/marker keys; opted-in HTML
responses stay private/no-store. Separate HTTP checks cover Inertia JSON too.

Native request preparation now removes the token query key while preserving
preview markers. Tests retain the forged-marker/valid-token case: its request
marker is present but fails native preview-marker validation, while the matched
native authorized draft remains previewing. Invalid/expired/unrelated tokens and
forged inputs alone still reveal no additional private data. No new token
checker, draft store, refresh polling or package-specific schema was added.

The unmapped Twig control uses the same ordinary Vue Preview and verifies field
autosave, public isolation, zero project iframe bundles and zero browser errors.
The previous author/sidebar WeakMap error does not occur on this official pair.

## Current checks and provenance

| Check | Result |
| --- | --- |
| Fresh client + SSR builds | Passed |
| PHP integration | 14 tests / 86 assertions passed; no PHP coverage driver, explicit `--no-coverage` |
| Vue full suite | 30 passed; required 100% line coverage met |
| STEP-01 routing/public regression | 37 passed |
| STEP-02 content/query regression | 5 passed |
| STEP-03 sites/token/privacy | 10 passed |
| Native View + standalone Vue previews | 4 passed (View and three sites) |
| Corrected ordinary Vue Preview + native Twig control | 4 passed |
| Static/format checks | TypeScript, Prettier, Composer validation, PHP syntax/formatter, scoped JS lint and whitespace checks |

Plugin `composer validate --strict` passes. The local root manifest is schema-valid
but its strict validation emits the pre-existing missing-license warning and
intentional exact development-pin warnings; those warnings are not represented
as a strict root validation pass.

The final upstream check snapshot contains 20 successful, 5 skipped and 2
cancelled checks (Windows SQLite and the Ubuntu PostgreSQL matrix jobs). It is
retained in `upstream-checks.json`; no claim that all upstream CI is green is
made. Local compatibility acceptance above is independently evidenced.

These are separate evidence runs, **not a single green 60-case run**. The first
60-case official run passed 56; four ordinary/Twig launches stopped on an
ambiguous selector because native Markdown also has a Preview toolbar button.
An exact-text selector then failed on custom-element text. The final selector
intersects the exact accessible button name with the native `craft-button`
control; it opens the real editor Preview. The corrected four cases all pass
with every original behavior/error assertion retained. The unaffected 52 public
cases and View/standalone cases are reused; no application or dependency change
followed those passes. No native/vendor code was patched.

Evidence is under the existing execution run's `step03/official-6x/` directory:
`validated-prepare/` holds exact locks, package and published asset manifests,
tree hashes and build logs; `browser/` and `editor-corrected/` retain the initial
selector failures and unaffected passes; `ordinary-preview/` holds the corrected
four passing cases. `php-tests.log`, `vue-tests.log`, `vue-coverage/`,
`native-api-notes.md`, `checks.json`, `review.md` and final source/patch snapshots
bind the implementation to this baseline. Historical alpha.20 evidence remains
in the preceding STEP-03 artifacts and the original report revision `2c670fe`.

Only the plugin PR is published. All Playground dependency/application changes
remain local and uncommitted at `f7dd95da555f704d49aa590f7fa60bf7d24f1731`.
No production deployment or original database upgrade is included. The human
requested a roadmap pause after STEP-03; no STEP-04 work is started or prepared.

---

# Historical alpha.20 validation

The project integration and bounded plugin criteria are implemented after merged
STEP-02 (`36030b4`). **Full editorial acceptance remains blocked** by the native
Craft `6.0.0-alpha.20` legacy Live Preview JavaScript error. No failing criterion
is waived, marked expected or represented as green. The concrete plugin PR is
reviewable independently of resolving that upstream CP dependency.

## Delivered behavior

The package runtime remains unchanged and schema-free. Project PHP supplies
Craft site identity (`id`, `uid`, `handle`, `name`, `language`, `baseUrl`), native
homepage/overview links and public translated-entry links. Project route groups
configure each domain/path and named destination; normal method/domain/URI
matching and opt-in public middleware remain intact. Craft chooses the site;
language and public query parameters do not grant authority.

The consuming project uses native `Url::siteUrl()` and element URLs. Public
translation queries retain normal publication rules and ignore placeholders;
unavailable translations are omitted. Site-switch anchors load a document,
including across origins. Native pagination/search/filter URLs keep the selected
site and base path. Request-relative project assets work on each origin.

Preview content comes from native `CurrentElement` and its `previewing` state,
including after native request preparation removes query markers. Named drafts,
unpublished content, user provisional drafts and revisions use Craft's token,
placeholder, site and publication machinery. No parallel token validation,
draft persistence, refresh polling or package content schema is introduced.
Both HTML and Inertia JSON retain `private, no-store`.

## Runtime acceptance and limits

The isolated PostgreSQL snapshot uses Craft `6.0.0-alpha.20`, Laravel `13.40.0`,
PHP `8.5.12`, Inertia Laravel `3.7.0`, Inertia Vue `3.11.0` and Vue `3.5.45`.
Two distinct `nl` sites cover `/nl/` and `nl.craft.test`. Chrome resolves the
latter through disposable launch arguments; HTTP checks use loopback plus Host.
No machine hosts/DNS files, real sites or original database/schema were changed.

| Criterion | Evidence and result |
| --- | --- |
| REQ-06: site identity, language, domain and subdirectory | Distinct site IDs/UIDs/handles, translated slugs/title/body/Markdown, relations/topics, globals and nested Matrix verified through native pages. |
| Native links and overview context | Homepage, overview, pagination, search/topic/sort and cross-origin document navigation verified for both variants. |
| REQ-07: version authorization | Named drafts, unpublished, provisional and revision content verified; unrelated/expired/invalid tokens and forged markers reveal no additional private content. |
| Prepared-request authority | Test-only response observation confirms token/marker removal and `Request::isPreview() === false` while matched `entry.previewing === true`; no production diagnostic is installed. |
| Ordinary editor preview launch | Native Vue editor **View** popup verified at the canonical primary, subdirectory and domain URLs, with independent public isolation. |
| Autosave/public isolation | Legacy native iframe title/summary/body refresh works on primary, subdirectory and domain sites without publication or manual refresh. All three strict browser-error assertions remain failed. |
| Native CP error | Fresh unmapped Twig control reproduces the same WeakMap exception, HTTP 200 and working autosave, with zero plugin/project bundles in the preview iframe. |

The ordinary alpha.20 Vue editor renders a **View** link, not an embedded Live
Preview launch control. The autosave check uses Craft's separate supported
standalone legacy `/admin/preview/...` route. A normal View popup launch must not
be described as proof of an ordinary-editor embedded autosave UI.

The exact native defect is a missing sidebar initializer target: legacy
`Preview._loadElementEditor()` inserts `data.content` and executes `bodyHtml`
but does not insert `data.sidebar`. The returned author selector markup exists
only in the sidebar. `Craft.BaseElementSelectInput` then passes an undefined
container to `WeakMap.set`, raising `Invalid value used as weak map key` in
`ui-CCfBrd3B.js`. Current native-control evidence records the selector ID and
content/sidebar presence. This is a current external acceptance blocker, not a
fixture waiver. Vendor/upstream files and assertions remain intact.

## Checks and evidence

- Fresh isolated client and SSR builds passed.
- PHP: 14 tests / 86 assertions passed. No PHP coverage driver is installed;
  `--no-coverage` is explicit and no PHP coverage claim is made.
- Vue: 30 tests passed; required full-suite line coverage is 100%.
- TypeScript, Prettier, Composer strict validation, scoped PHP CS Fixer,
  JavaScript lint, PHP syntax and whitespace checks passed.
- STEP-01/02 browser regression: 42 passing cases.
- STEP-03 site/token/browser checks: 10 passed from a clean snapshot after the fixture cache correction.
- Final editor project: 1 passed (ordinary View launch), 3 failed (primary and both site legacy autosave cases, each only at the final strict browser-error assertion). Complete suite is not green.

Run a fresh snapshot with `prepare.mjs --fresh --working-tree`, then
`npm run test:e2e`. STEP-03 depends on STEP-01/02 and seeds its extra sites in
`beforeAll` only after those predecessor checks. The test database guard in
`fixtures.php` prevents seeding the source app database. Do not reuse the expanded
site snapshot for baseline pagination counts; prepare fresh for the full suite.

Operational evidence is under
`/Users/joostwaaijer/.codex/agent-team/runs/craft-inertia-execution-20261109/step03/`:
`cache-correction-prepare/`, `cache-corrected-browser/`, `site-editor/`,
`php-tests.log`, `native-control/`,
`preview-source-analysis.md`, `checks.json`, `review.md` and the aggregate and
incremental Playground snapshots/patches. `final-browser/` retains the 42 passing baseline cases and an earlier 52/54 run:
the Matrix fixture failure in that run is superseded by the clean 10/10 site
run; its native CP failure persists. That fixture correction refreshes native
field and entry-type caches after changing localization, before consuming cloned
layout fields. Passing PHP/Vue and baseline browser checks apply unchanged:
only the isolated site seeding and added site-editor cases changed afterward.
Earlier unsuccessful fixture runs are retained separately; they are not passing evidence. The source analysis clearly
distinguishes predecessor source inspection from fresh runtime reproduction.

Playground remains on `feature/native-inertia-pages` at
`f7dd95da555f704d49aa590f7fa60bf7d24f1731`, with all prerequisite work preserved
and STEP-03 changes local/uncommitted. No Playground commit, push or PR is made.
Only the plugin branch is published. No merge, deployment, real database change
or later roadmap step is included.
