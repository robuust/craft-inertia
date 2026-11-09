# STEP-03: native preview and multisite

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
