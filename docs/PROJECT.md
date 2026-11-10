# Craft Inertia project initialization

The product is a reusable Craft CMS 6 plugin that lets projects replace their
complete public frontend with Inertia and Vue. Craft keeps ownership of content,
URLs, editorial workflows, sessions and permissions. Each project supplies its
Vue presentation and chooses the data it exposes.

**Current execution:** STEP-01 through STEP-04 and independent STEP-06A are merged. The human's
2026-11-10 independent-work decision authorizes STEP-06A guest public SSR,
metadata, errors/maintenance and asset versions while STEP-05 stays open.
The next independent STEP-06B adds project opt-in indexing and safe JSON-LD;
it does not close native account defects or public caching/invalidation.
Conservative private/no-store responses remain; full AC-06 and account-dependent
caching are incomplete. Approved official CMS `0506c32` plus matching assets
`1797325` remains fixed. Tests and one plugin PR are authorized; all Playground
integration stays local and uncommitted. See [rendering](RENDERING.md) and
[STEP-06A evidence](../reports/e2e/STEP06A.md) and [STEP-06B evidence](../reports/e2e/STEP06B.md).

**Initialized:** 2026-11-09. This is research and documentation, with a draft
execution roadmap. Product implementation, new test work and publication are
separate actions. This initialization records the original bounded article PoC.
STEP-01 replaces its fixed runtime with opt-in public middleware and
project-owned pages; STEP-02 documents project-owned native content and queries
in [CONTENT.md](CONTENT.md); see the [current integration](../README.md). The source
register and original E2E findings below retain their historical meaning.

## Project entrypoints

- [Scope, requirement IDs, acceptance criteria and roadmap](FRONTEND.md).
- [Domain glossary](../CONTEXT.md) and [data and response contracts](DOMAIN_MODEL.md).
- [Installation and current demonstration](../README.md).
- [Existing E2E report and evidence](../reports/e2e/REPORT.md).

`FRONTEND.md` remains the single scope and roadmap document. There is no parallel
requirements tracker or second roadmap created by initialization.

## Inspected repositories and versions

| Repository                                                                  | Inspected basis                                                                                                                                                                         | Role                                                             |
| --------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| [joostwaaijer/craft-inertia](https://github.com/joostwaaijer/craft-inertia) | `feature/inertia-proof-of-concept`, `aa361006dc985fa7c6343c8c84372adf58f2b7e0`; clean before documentation edits. Local folder `/Users/joostwaaijer/Documents/workspace/craft-inertia`. | Private plugin repository and owner of these documents.          |
| [robuust/craft6-playground](https://github.com/robuust/craft6-playground)   | `develop`, `f7dd95da555f704d49aa590f7fa60bf7d24f1731`; clean, inspected read-only.                                                                                                      | Consuming application and future example/acceptance environment. |

The E2E baseline used Craft `6.0.0-alpha.20`, Laravel `13.40.0`,
`inertiajs/inertia-laravel` `3.7.0`, `@inertiajs/vue3` `3.11.0`, Vue `3.5.45`,
PHP `8.5.12` and Node `24.15.0` (S05). The Playground lockfiles agree on the
PHP versions. Its Vite is `8.3.6`; the separate PoC package uses `8.3.7`.
These are inspected/tested versions, not proof that all allowed Composer/npm
version ranges work.

The GitHub repository and local folder are named `craft-inertia`; the package still
uses Composer name `robuust/craft-inertia-poc`, namespace `Robuust\CraftInertiaPoc`
and plugin handle `inertia-poc`. Initialization does not rename installed package
identities or make a distribution/license decision.

## Source register

All entries were inspected on **2026-11-09**. Stable revisions and version tags
take precedence over moving documentation for claims about installed behavior.

| ID  | Source and version                                                                                                                                                                                                                                                             | Coverage and limits                                                                                                                                                                                                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| S01 | User decisions in this chat: reusable plugin for Craft sites; full public frontend scope; project-owned Vue pages; private repository and workspace rename; current `agent-team init`.                                                                                         | Product direction is confirmed. No specific existing site, visual design, Figma/Balsamiq file, Trello board or third-party compatibility list was supplied.                                                                                                  |
| S02 | Plugin at the revision above: [source](../src), [routes](../routes/web.php), [configuration](../config/inertia-poc.php), [Vue application](../resources/js), [Composer](../composer.json) and [npm](../package.json) manifests.                                                | Relevant implementation and build entrypoints inspected. Fixed demo routes, projections, page resolver and response policies; no reusable frontend implementation yet. No CI workflow exists in this repository at the inspected revision.                   |
| S03 | Playground at the revision above: `routes/web.php`, `app/Http`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `resources/js`, lockfiles and committed project config.                                                                                           | Existing authenticated Inertia/SSR example; one site, no committed content sections/fields. `/` currently redirects to `/users`. The original checkout was not changed.                                                                                      |
| S04 | Installed alpha.20 framework in the ignored E2E snapshot; canonical [tagged source](https://github.com/craftcms/cms/tree/6.0.0-alpha.20/src). Targeted routing, site, preview, action/auth, content, asset and cache paths inspected.                                          | Establishes APIs and control flow, not executed behavior. The snapshot is an installation artifact, not a Git worktree or another authoritative Playground revision.                                                                                         |
| S05 | [E2E report](../reports/e2e/REPORT.md), [environment](../reports/e2e/environment.json), [source manifest](../reports/e2e/source-manifest.json), [JUnit](../reports/e2e/junit.xml), native-preview evidence and existing test source.                                           | Saved run: 27 tests, 26 passed, 1 failed. The 13 application files still match the saved hashes. No tests were rerun during init. Report coverage is the demo, not the target plugin.                                                                        |
| S06 | GitHub issues for both repositories and PR inventory for the plugin, inspected through GitHub MCP; recent 100 Playground PR summaries also inspected.                                                                                                                          | Both issue lists returned zero with pagination exhausted; the plugin has no PRs. Historical Playground PR pagination was not exhausted; titles are not treated as new requirements or runtime evidence. No tracker changes made.                             |
| S07 | [Craft roadmap](https://craftcms.com/roadmap), [alpha announcement](https://craftcms.com/blog/craft-6-alpha-released), [alpha.20 release](https://github.com/craftcms/cms/releases/tag/6.0.0-alpha.20), [merged routing PR 19762](https://github.com/craftcms/cms/pull/19762). | Published Inertia work names the CP. Native section/Matrix route destinations merged 2026-09-30. No official ready-made public frontend adapter commitment was found in the bounded public-source search; this is not a claim about private plans.           |
| S08 | Official [Inertia v3 server setup](https://inertiajs.com/docs/v3/installation/server-side-setup), [validation](https://inertiajs.com/docs/v3/the-basics/validation), and [separate-root discussion](https://github.com/inertiajs/inertia-laravel/issues/730).                  | Official Laravel adapter is the transport owner. Moving docs must be checked against installed versions before implementation.                                                                                                                               |
| S09 | RareForm [2.2.1 Composer metadata](https://github.com/RareFormLabs/craft-inertia/blob/2.2.1/composer.json) and README; [Craft 6 community PoC](https://gist.github.com/wsydney76/d9cf101d41cf31425e5c18edb5d080f4), created 2026-09-24.                                        | RareForm's tagged package requires Craft 5, so it is a reference rather than demonstrated Craft 6 compatibility. The separate gist demonstrates middleware/controller/Vue setup and identifies itself as a PoC. Neither establishes complete feature parity. |

## Coverage and current behavior

| Area                          | Coverage                                   | Result and remaining work                                                                                                                                                                       |
| ----------------------------- | ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Product and actors            | Inspected                                  | Reusable plugin; visitors, signed-in visitors, editors, project developers and operators. See domain contracts.                                                                                 |
| Public routes and states      | Inspected source; partial runtime evidence | Demo overview/detail, empty/missing section, unavailable entry, 404, desktop/mobile and Inertia history were exercised. Native canonical/home/dynamic routing remains STEP-01.                  |
| Domain and data               | Inspected                                  | Content/schema and state stay in Craft; explicit project props. No new content database is needed. Complex content/query/file-operation contracts remain STEP-02/04.                            |
| Editorial flows               | Inspected source; partial runtime evidence | Demo autosave preview and public separation work functionally. Named/unpublished token boundaries were exercised. Native canonical previews, revisions and multisite remain STEP-03.            |
| Forms, accounts and failures  | Inspected framework; target unimplemented  | Native actions, flash/session errors, account challenges and project auth views need explicit integration. STEP-04/05 own this.                                                                 |
| Rendering, SEO and operations | Inspected source; partial runtime evidence | Package-owned client rendering coexists with host SSR in the saved run. Target SSR, metadata, response caching/invalidation and production deployment remain STEP-06/07.                        |
| Other plugins                 | Partial research                           | Reference projects exist; no third-party integration is validated. STEP-07 needs a selected product/version list.                                                                               |
| Designs and accessibility     | No design source supplied                  | Project-specific design is not a plugin requirement. Examples must remain usable; full site visual/accessibility acceptance belongs to each consuming project. No visual conformity is claimed. |
| Backlog and delivery          | Inspected as bounded in S06                | Existing scope document owns requirements; no issue publication is part of init. No new product code is delivered by these docs.                                                                |
| Verification                  | Existing evidence inspected                | Saved E2E result remains 26/27. New checks must target the changed contracts when separately authorized. Inspection and documentation checks do not establish runtime acceptance.               |

## Findings that affect implementation

**Prefer native destinations.** Craft already supports named Laravel routes and
controllers in section/Matrix site settings (S04/S07). STEP-01 should compose
that capability with public Inertia middleware, project pages and explicit data.
`SetRoute` is available but is not the default justification for a parallel route
registry. It runs before some entry live/URL guards, while registered destination
matching follows a different path; a broad listener can change native behavior.

**Route collisions need a concrete policy.** Native named destinations must
match URI/domain/method. Normal routes precede fallback, but dynamic project
routes register as view routes at runtime and identical registrations can
replace earlier entries. Homepage resolution exists natively; the Playground's
explicit `/` redirect currently intercepts it. A new catch-all is unnecessary
until an evidenced limitation requires one.

**The public shell needs its own scope.** Craft's native Inertia middleware is
CP-oriented. Its `isSiteRequest()` predicate alone does not exclude every action
or API request. The integration must deliberately attach its public root view,
props, asset versioning and SSR policy without taking over unrelated endpoints.

**Native action semantics matter.** Preserve native field names and validation.
Some failures have only flash/error-code data; an empty error bag is not proof of
success. Native auth GET rendering can fall back to the CP shell; the Playground
login example is not a replacement for every native authentication challenge.

**Preview policy must cover both representations.** Consume Craft's authorized
element/site state, and verify cache headers on HTML and Inertia JSON. Native
header middleware contains response-type checks, so public JSON isolation must
be demonstrated rather than inferred from a working HTML preview.

## Decisions and readiness

| Decision                                                                     | Status and effect                                                                                                                                                                                          |
| ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Reusable full public frontend, project Vue presentation, Craft-owned backend | Confirmed by S01; retained throughout the requirements and model.                                                                                                                                          |
| Official Inertia Laravel adapter owns protocol handling                      | Observed dependency and proposed continued approach; no second transport implementation justified.                                                                                                         |
| Native Craft destinations before custom mapping abstraction                  | Proposed implementation default based on S04/S07; STEP-01 selects its minimal supported API.                                                                                                               |
| Third-party plugin support                                                   | Capability required, initial products/versions still open. Does not block STEP-01; blocks advertising specific integrations.                                                                               |
| Permanent package name, license and distribution channel                     | Open release decisions; preserve current identifiers until intentionally migrated in STEP-07. Private GitHub hosting does not decide future distribution.                                                  |
| Test and publication authority                                               | Init authorizes research/docs. It does not authorize new test work, product edits, commits, pushes, issue/PR publication, merge or deployment. Earlier E2E/commit work was completed within its own scope. |

At initialization, STEP-01 was **technically READY** against pinned alpha.20. Later steps have named
dependencies; their readiness and acceptance criteria are in `FRONTEND.md`.
Readiness does not grant execution authority. Subsequent authorized roadmap execution delivered STEP-01/02. STEP-03 now
records its bounded implementation and approved development-baseline acceptance; see the current execution note above.

## Evidence and operational limits

The Live Preview E2E case was expected to pass and remains failed. Its functional
assertions passed; the unhandled `WeakMap` exception was also reproduced in a
native Twig baseline with zero plugin requests (S05). The exact upstream control
or fix is unresolved. Do not waive the failure or call the suite green.

The saved test services were stopped and the dedicated database removed. The
folder rename repaired the ignored runtime's plugin symlink; some generated
Composer metadata and compiled-view provenance still contain the old path.
The tracked preparation script derives the package root dynamically. Recreate
the isolated environment during a separately authorized future E2E run instead
of treating generated path records as source defects.

The operational init plan/state is stored outside product repositories at
`/Users/joostwaaijer/.codex/agent-team/runs/craft-inertia-init-20261109/`.
This holds scheduling and review evidence, not a second product specification.
No schema, dependencies, production data or original Playground files were
changed by initialization.
