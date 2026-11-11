# Reusable Craft frontend plugin

The plugin must let a Craft site implement its complete public frontend with
Inertia and Vue. Each project supplies its own pages, layouts and presentation.
Craft remains responsible for content, URLs, publication rules, sessions,
permissions and preview authorization.

This is the agreed product direction following the initial proof of concept.
The capabilities below are delivery requirements, not claims about the current
package. The historical [E2E report](../reports/e2e/REPORT.md) covers only the initial
fixed article demonstration. STEP-01 through STEP-04 replace it with native
destinations and project-owned pages; their slice reports record scoped evidence. [PROJECT.md](PROJECT.md) records the inspected sources,
coverage and decisions; [DOMAIN_MODEL.md](DOMAIN_MODEL.md) defines the data and
response boundaries. This file owns the requirements and draft roadmap.

## Approved native entry templates

The 2026-11-11 human decision adds native Template values such as
`inertia:Site/Articles/Show` and component-keyed explicit field selection.
This changes the earlier project-owned generic serialization boundary: the
plugin now owns reusable entry/site/preview context and bounded serialization
of selected core fields, assets, relations and Matrix children. Projects retain
Vue/layout/build, field selection, custom serializers, special queries and
business rules. [CONTENT.md](CONTENT.md) defines the current prop contract;
[INSTALLATION.md](INSTALLATION.md) defines the minimal consumer setup.
[Native-template evidence](../reports/e2e/ENTRY_TEMPLATES.md) records beta.1
verification, preservation and material limits.

Native URI/site/publication/preview resolution remains authoritative. Installing
the package captures no page without the exact `inertia:` template prefix.
Explicit native destinations keep precedence. No project entry controller or
per-entry Laravel route is required for mapped templates. Manual integrations,
Twig/Blade, APIs, feeds, actions and CP retain their owning behavior. Disabling
the plugin restores native template interpretation; an `inertia:`-only template
requires an explicitly configured native fallback, not automatic reconstruction.

## Ownership

| Owner         | Responsibility                                                                                                                                                               |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Craft         | Content models and queries, element URLs, site and locale resolution, publication status, drafts, revisions, preview tokens, accounts and permissions.                       |
| This plugin   | Connect Craft site requests and application responses to the official Inertia adapter; provide native entry-template rendering, selected-field serialization, public page context, response policies and integration points. |
| Each project  | Vue pages, layouts, navigation, styling and accessibility; field selection, custom serializers, PHP queries and explicit bespoke projections for business rules.                              |
| Other plugins | Their own domain behavior, such as Commerce calculations, form processing or SEO metadata. An integration exposes that behavior to the Vue frontend.                         |

A site must be able to use the plugin without an `articles` section, the
`summary` and `body` fields, demo routes or a package-owned design. Moving the
example pages and content setup into the Playground is part of separating the
reusable package from its demonstration.

Replacing the frontend includes reproducing the behavior previously expressed
in templates. Twig functions, filters, tags and widgets need a PHP data or action
equivalent and a Vue presentation where applicable. Installing the plugin will
not translate existing templates automatically.

## Required capabilities

REQ-01 through REQ-13 preserve the previously agreed capability scope. Their
acceptance criteria are owned by the delivery steps below; a supporting earlier
step does not by itself complete the full requirement. Product authority is the
user's reusable-frontend decision (PROJECT source S01), informed by source and
E2E observations S02-S05. Third-party product selection remains open.

| Requirement | Capability               | Required observable behavior                                                                                                                                                                                   | Historical proof of concept                                                                         | Owning step |
| ----------- | ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ----------- |
| REQ-01      | Native element URLs      | A section's normal entry URL opens the configured Vue page. Craft still resolves the element and applies publication rules. Homepage entries, singles, structures and nested URLs work.                        | Separate numeric demo URLs only.                                                                 | STEP-01     |
| REQ-02      | Other site routes        | Overview, search and other project pages can use named Laravel routes and Craft dynamic route configuration. Static pages, feeds and non-HTML endpoints keep their appropriate response type.                  | One fixed overview.                                                                              | STEP-01     |
| REQ-03      | Content                  | Explicit public props support core field values, relations, globals, taxonomy, rich text and nested Matrix content. Project or plugin field types have a supported extension point.                            | Title, date and two optional plain text fields.                                                  | STEP-02     |
| REQ-04      | Assets                   | Craft generates asset URLs, transforms and responsive image data. Authorized uploads and downloads retain native validation and access rules.                                                                  | No asset integration.                                                                            | STEP-04     |
| REQ-05      | Queries                  | Search, filters, sorting and pagination use Craft queries and shareable URLs. Projects choose query scope and public projections.                                                                              | Latest 20 live entries.                                                                          | STEP-02     |
| REQ-06      | Multisite                | Language, domain, subdirectory, site identity and translated URLs follow Craft's current site. Cross-site navigation uses the appropriate URL and full-page navigation when required.                          | One site at the origin root.                                                                     | STEP-03     |
| REQ-07      | Preview                  | Native draft, revision and provisional-draft preview displays the authorized element at its site URL, refreshes after autosave and remains separate from public visits.                                        | Demo preview works functionally; one native Craft CP JavaScript error remains in the E2E result. | STEP-03     |
| REQ-08      | Forms and actions        | Mutations preserve CSRF, validation errors, old input, error bags, flash messages, redirects and upload behavior through the normal Inertia form flow.                                                         | Not implemented.                                                                                 | STEP-04     |
| REQ-09      | Accounts and permissions | Login, logout, registration, activation, password reset, protected pages and applicable account challenges use Craft's existing authentication and authorization. Public props expose only intended user data. | Host authentication is excluded from demo props.                                                 | STEP-05     |
| REQ-10      | Errors and redirects     | Missing or unavailable content, authorization errors, expired sessions and maintenance have correct HTTP status and Vue presentation where appropriate. Existing redirects keep their destination and status.  | A demo entry 404 page.                                                                           | STEP-06     |
| REQ-11      | SEO and rendering        | Projects can render initial HTML through SSR and supply titles, metadata, canonical URLs, hreflang and structured data. Public pages are indexable according to site policy.                                   | Client rendering; all demo responses are noindex.                                                | STEP-06     |
| REQ-12      | Cache and deployment     | HTML and Inertia JSON have correct variation and asset versions. Preview and private responses cannot enter public caches. Public caching and invalidation respect Craft dependencies and publication changes. | All demo responses are private/no-store.                                                         | STEP-06     |
| REQ-13      | Other plugins            | Documented integrations reuse the owning plugin's APIs, actions and permissions. Compatibility names the plugin, version and supported features.                                                               | No integrations validated.                                                                       | STEP-07     |

The core target includes all of these capability groups. Commerce, form builders
and SEO plugins require separate compatibility work for each selected product;
their template interfaces alone cannot establish Vue compatibility. The package
must offer extension points without claiming that every third-party plugin
already works.

## Integration approach

Keep Craft's route and element resolution in place. Configure which resolved
elements use which project pages and data providers. A mapped entry should move
from its Twig response to an Inertia response at the same URL, including the
correct current site and any server-authorized preview element.

Craft beta.1 retains native section/Matrix destinations for named
Laravel routes and controllers, with [`CurrentElement`](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Route/CurrentElement.php)
injection. Prefer composing that configuration before adding a parallel mapping
registry. Named destinations must match the URI, domain and method; native
[`ElementRoute`](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Route/ElementRoute.php)
checks these constraints. The [`SetRoute` event](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Element/Events/SetRoute.php)
selects the approved `inertia:` entry templates. Its listener preserves native
live/preview and has-URLs checks, explicit destinations and earlier handled
decisions before returning the generic controller. It does not register URLs.

STEP-01 must establish route precedence, including native homepage resolution,
the Playground's current `/` redirect and project-config dynamic view routes.
Identical method/domain/URI registrations can replace one another; do not assume
every explicit route always wins. Controller fallback also needs deliberate
method/middleware handling. These are source-inspected constraints, not new
runtime acceptance evidence (PROJECT S04/S07).

Use the official [Inertia Laravel adapter](https://inertiajs.com/docs/v3/installation/server-side-setup)
for response negotiation, redirects, shared data and asset versioning. A Vue
page is an HTTP response, not a replacement string renderer: Craft's
[`TemplateRendererInterface`](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/View/Contracts/TemplateRendererInterface.php)
returns strings and cannot by itself supply the complete Inertia protocol.

The project owns one public Vue application and its Vite and SSR builds. The
package must coexist with Craft's separate control-panel frontend. Public page
middleware must not inherit CP props or a CP root view. Manual project routes
retain intentional host shares. Native mapped pages isolate and clear the cloned
response factory, then restore the host factory. The package ships no frontend.
Responses retain conservative private/no-store and default-deny indexing;
production acceptance and full cache policy remain open.

Data providers return explicit public props. Do not serialize entire elements,
field layouts, users or queries into page data. Resolve related content and
asset transformations in PHP through their existing owners. Field presentation
can vary by project without introducing a parallel content model.

Craft validates preview tokens and installs preview placeholders. Consume that
resolved state rather than trusting URL markers or implementing a second token
system. In the installed alpha, preview preparation can remove query markers;
marker presence alone is not a reliable preview contract.

Keep native action behavior intact. Some Craft actions respond with JSON when
the request expects JSON, while Inertia validation normally uses redirects and
session errors. The forms slice must check each supported action contract and
bridge only the mismatch; it must not add parallel validation or convert every
JSON action response globally.

## Delivery roadmap

Each slice adds a complete observable capability to the Playground and records
its support status. Tests need the user's explicit authorization; the acceptance
criteria below do not grant permission to write or run them.

Planning basis: 2026-11-09, the revisions and source register in PROJECT.md.
STEP-01 is delivered and merged in [PR #1](https://github.com/joostwaaijer/craft-inertia/pull/1).
STEP-02 implementation and evidence are recorded in [STEP02.md](../reports/e2e/STEP02.md).
STEP-03/04 are also delivered and merged. STEP-05 remains open. STEP-06A is the
human-authorized independent public subset described below. Implementation, independent review,
publication, merge, deployment and runtime acceptance must be recorded
separately; the prior PoC commit does not complete any target step.

| Step                                            | Owning requirements                           | Prerequisites                                                                            | Affected repositories                            | Technical readiness                                                                                                       |
| ----------------------------------------------- | --------------------------------------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| STEP-01 Native URLs and project pages           | REQ-01, REQ-02                                | Existing Craft destinations and the agreed ownership model                               | Plugin + Playground                              | READY; no unresolved product decision prevents the bounded slice.                                                         |
| STEP-02 Content and queries                     | REQ-03, REQ-05; supports REQ-04               | STEP-01 page/data contract                                                               | Plugin + Playground                              | READY after merged STEP-01; see STEP-02 evidence.                                                                                     |
| STEP-03 Native preview and multisite            | REQ-06, REQ-07                                | STEP-01/02 mapped pages and content                                                      | Plugin + Playground                              | Implemented on the approved official 6.x baseline; see STEP-03 evidence. The pause was revoked by the human resume on 2026-11-10.                                                                               |
| STEP-04 Forms and file operations               | REQ-04, REQ-08                                | STEP-01 public middleware, STEP-02 assets, STEP-03 site/preview context                  | Plugin + Playground                              | Implemented; see STEP-04 evidence and its current publication/merge state.                                                       |
| STEP-05 Accounts and access                     | REQ-09                                        | STEP-04 action/flash/error contract                                                      | Plugin + Playground                              | OPEN: preserved, unaccepted account work is blocked by native account behavior; no correction was authorized.                                                                                     |
| STEP-06 Rendering and response policies         | REQ-10, REQ-11, REQ-12                        | STEP-02/03 content/site dependencies and STEP-05 private-session behavior                | Plugin + Playground                              | PARTIAL: independent STEP-06A/06B/06C are authorized without STEP-05; HTTP output caching, account-dependent caching and full AC-06 remain deferred.                                                                               |
| STEP-07A Independent clean installation and fallback | AC-07 installation/disable subset | Merged STEP-01/02/03/04 and independent STEP-06A/06B/06C | Plugin; isolated neutral application | Implemented; scoped evidence in STEP07A.md, independent review and merge tracked separately. Full STEP-07 remains incomplete. |
| STEP-07B Optional native Contact Form | REQ-13; bounded AC-07 integration | Merged STEP-07A; human-selected Contact Form exact official commit | Plugin docs; isolated optional project fixture | Scalar flow exercised; native nested-message limitation remains failing. See CONTACT_FORM.md and STEP07B.md; independent review/merge tracked separately. |
| STEP-07C Permanent private package identity | AC-07 identity/upgrade subset | Merged STEP-07B; human identity/license/distribution choices | Plugin + disposable legacy/current consumers; local source patches only for Playground | Implemented; bounded proof in STEP07C.md; independent review/merge tracked separately. |
| STEP-07 Reusable installation and compatibility | REQ-13; release evidence for all requirements | STEP-01 through STEP-06; named third-party products/versions for advertised integrations | Plugin + Playground; selected integration owners | PARTLY_READY for remaining compatibility/release acceptance; implementation and compatibility acceptance depend on predecessors. |

The current execution follows the user-authorized roadmap with scoped tests and one fresh plugin PR per slice; Playground changes remain local and uncommitted. All step owners must read the
project/model and relevant pinned sources before implementation. If a step is
published through PRs, its required PRs must merge before dependent roadmap work
starts; each slice records its actual PR and merge state. Each handoff reports exact
scope, criterion-level evidence and remaining findings, source revision/local
changes, relevant PRs and live merge state, plus deployment/acceptance limits.
No step may imply permission to create chats, publish issues or change upstream.

### STEP-01 Native URLs and project pages

**Owner and contracts:** one implementation owner coordinates plugin and
Playground changes. REQ-01/02; DOMAIN_MODEL target request contract; PROJECT
S02-S04/S07/S08. The first task selects the minimal native destination/public
middleware/root/page/build configuration from concrete examples.

Introduce opt-in project-page configuration and explicit PHP data providers.
Use the project's public root view, page resolver and asset build. Keep unmapped
requests under their existing route handling. Extract the article demonstration
from the reusable runtime once the project integration can replace it.

**AC-01:** two sections with different fields render different project Vue
pages at their Craft URLs; a homepage single and an overview work without the
demo prefix; direct and Inertia visits agree; unavailable entries remain
unavailable; explicit route precedence is documented; CP, actions, assets and
GraphQL retain their existing handling. A second example configuration uses
different section and field handles without modifying package source.

**Evidence and scope:** inspect native destination/method/middleware behavior;
format/static/build checks plus authorized integration/browser checks must show
the above outcomes and response headers on both HTML and JSON. Preserve preview
privacy immediately; complete multisite/preview acceptance belongs to STEP-03.
Forms, new field serializers, full SSR/SEO and public caching are outside this
slice. Existing E2E counts cover neither this routing nor the new shell.

### STEP-02 Content and queries

**Owner and contracts:** one content/query owner across plugin and examples;
REQ-03/05, supporting asset projections for REQ-04. Depends on STEP-01's page and
data contract; DOMAIN_MODEL public projection rules; PROJECT S03/S04.

Provide the smallest shared conventions needed for public content and asset
projections. Add Playground examples for relations, rich text, nested Matrix,
globals, taxonomy, transforms, search, sorting and pagination. Keep schema and
query choices in the project.

**AC-02:** the same package supports two distinct content schemas; query
parameters survive navigation and browser history; nested content and missing
optional fields render correctly without exposing internal element data.

**Evidence and scope:** compare projected output with Craft query/field/transform
results, check relation loading, links and pagination through authorized
integration checks. Uploads and protected downloads complete in STEP-04;
third-party field support needs a named compatibility integration.

### STEP-03 Native preview and multisite

**Owner and contracts:** one editorial/site-context owner; REQ-06/07. Depends on
STEP-01/02; DOMAIN_MODEL preview lifecycle and PROJECT S04/S05.

Integrate preview with the mapped canonical pages. Carry Craft's current site
through routing, props, links and response policies. Cover published, named and
provisional drafts, unpublished entries and revisions as supported by Craft.

**AC-03:** autosave updates the native preview while an independent public
visit sees published content; invalid or unrelated tokens reveal no draft data;
language, domain and subdirectory sites resolve correctly. Track the existing
native CP error separately from the plugin's preview behavior.

**Evidence and scope:** authorized real-editor/browser checks include ordinary
editor preview launch, saved/provisional/unpublished versions, revisions,
cross-site navigation and HTML/JSON cache headers. Preserve the existing failing
native baseline result until resolved; an upstream CP fix is not silently added
to this plugin scope. No parallel token or draft-persistence system.

### STEP-04 Forms and file operations

**Owner and contracts:** one public-action integration owner; REQ-04/08. Depends
on STEP-01/02/03 and native action/flash/file APIs; DOMAIN_MODEL mutations and
failures; PROJECT S04/S08.

Connect native and project actions to Inertia forms. Define error, flash,
redirect and JSON-action handling from actual Craft contracts. Include uploads
and protected downloads.

**AC-04:** valid and invalid submissions, CSRF failures, multiple error bags,
file validation and post-submit redirects work without duplicate validation or
client-side copies of server state. Message-only failures cannot appear as
success; authorized/private file access remains enforced by its owner.

**Evidence and scope:** inventory native response types/field names, then use
authorized success/failure/CSRF/upload/download checks. Do not globally rewrite
JSON responses. Native account lifecycle integration follows in STEP-05;
third-party form builders are separate STEP-07 compatibility work.

### STEP-05 Accounts and access

**Owner and contracts:** one authentication integration owner; REQ-09. Depends
on STEP-04's action/error/flash contract; DOMAIN_MODEL actors/authority and
PROJECT S04. Inspect native authentication views and challenges instead of
treating the Playground login example as complete native behavior.

Provide integration examples for native account flows and protected pages.
Share a deliberate public user projection and preserve Craft's session guard,
authorization and redirect behavior.

**AC-05:** login, logout, registration, activation, password reset, expired
sessions and applicable account challenges work through the Vue frontend;
guests and unauthorized users cannot access protected props or operations.

**Evidence and scope:** authorized session/account checks cover success,
failures, redirects, native rate limiting and applicable 2FA/confirmation steps.
Preserve native names such as `loginName` and `rememberMe` when consuming native
login actions. Project auth GET pages must use its public Vue shell. External
identity providers and custom account policy are not added without a concrete
project requirement or selected compatibility integration.

### STEP-06 Rendering and response policies

**Owner and contracts:** one rendering/HTTP-policy owner; REQ-10/11/12. Depends
on STEP-02/03 data/site lifecycle and STEP-05 private-session behavior;
DOMAIN_MODEL target response contract and PROJECT S04/S08.

Add public SSR, metadata, canonical and alternate links, error pages, maintenance
behavior, cache variation and invalidation. Separate public, authenticated and
preview response policies. Support deploy-time asset version changes.

**AC-06:** an initial public request contains meaningful HTML and metadata;
direct visits and Inertia navigation preserve status and redirect behavior;
caches distinguish HTML from page JSON and never mix sites, users or previews;
publishing or changing content invalidates affected output.

**Evidence and scope:** build client/SSR artifacts; authorized HTTP/browser
checks cover hydration, metadata, errors/maintenance, asset version transitions,
cache variation and invalidation. No production deployment or performance claim
follows from successful local builds.

### STEP-06A Independent public rendering

On 2026-11-10 the human explicitly chose independent work without correcting the
native account blockers. This is a bounded exception to STEP-06's STEP-05 gate,
not acceptance of STEP-05 or the complete STEP-06 capability. Guest public SSR,
project metadata, public error/maintenance responses and Vite asset versions
may proceed in a fresh plugin PR. HTML and page JSON remain private/no-store;
full authenticated lifecycle, cache variation/invalidation, indexing and
structured-data policy remain deferred. No native account workaround or STEP-05
source is included. See [rendering contracts](RENDERING.md) and
[STEP-06A evidence](../reports/e2e/STEP06A.md).

### STEP-06B Independent public indexing and structured data

The human's next-independent-slice instruction authorizes opt-in project indexing
and safe JSON-LD in one fresh plugin PR, with scoped tests and independent review.
Native account defects stay open. The default remains noindex/nofollow; selected
successful guest content may opt in while native disallowRobots, server-derived
token/preview/session exclusions and private/no-store responses remain enforced.
The project owns a minimal WebPage projection, schema choice and safe Inertia
Head lifecycle. Playground changes stay local/uncommitted. No public cache,
invalidation, native account fix, production policy or full AC-06 acceptance is
included. See [rendering contracts](RENDERING.md) and
[STEP-06B evidence](../reports/e2e/STEP06B.md).

### STEP-06C Independent public content-data caching

This bounded REQ-12 extension reuses native Craft dependency collection and
dependency-aware storage for explicitly selected published-guest article detail
and filtered/paginated overview data. Project opt-in defaults off. Site identity,
normalized query/page inputs and schema/projection inputs isolate the data.
Native saves, publication/unpublication, deletion and projected relation/nested
changes invalidate it; the first native publication/expiry boundary bounds its
lifetime, including empty queries and off-page content.

Only explicit public arrays are cached. Craft still resolves the current element
and publication/preview authority on every request. HTML and Inertia JSON reuse
data while regenerating policy, head/privacy, session, protocol and errors.
Responses remain private/no-store. Native APIs own dependency tags and
invalidation: no plugin watcher, cache registry or parallel protocol adapter is
introduced. The checked project example and local Playground integration are
the deliverable; the plugin runtime API remains unchanged. Playground changes
remain local/uncommitted. Full HTTP/CDN/browser caching, account lifecycle,
full AC-06, production acceptance and release remain deferred. See
[rendering/cache contracts](RENDERING.md) and [STEP-06C evidence](../reports/e2e/STEP06C.md).

### STEP-07 Reusable installation and plugin compatibility

**Owner and contracts:** package/release owner with each selected integration's
domain owner. REQ-13 and release evidence for all requirements; depends on
STEP-01 through STEP-06 and explicit plugin/version selections. PROJECT S07/S09
provide reference implementations, not blanket compatibility.

Document a clean installation, project configuration, local development,
production builds, SSR deployment, upgrade requirements and migration from
Twig. Select third-party integrations explicitly and publish a versioned
capability matrix for them.

**AC-07:** the package installs in a clean supported Craft application and
the Playground without package source changes; no demo schema or UI is
required; disabling it restores the project's configured native handling;
each advertised third-party integration has its own acceptance evidence.

**Evidence and scope:** establish permanent Composer/namespace/handle migration,
license and distribution choices; verify clean install, upgrade and disabling
behavior under separately authorized checks. Release docs retain exact
supported versions and deployment requirements. Publishing, changing repository
visibility, contacting maintainers and production deployment each retain their
own authorization boundary.

### STEP-07A Independent clean installation and native fallback

This bounded AC-07 subset depends on merged STEP-01/02/03/04 and the independent
STEP-06A/06B/06C public contracts. It can proceed while native STEP-05 account
defects remain open. The [clean installation checklist](INSTALLATION.md) and
[STEP-07A report](../reports/e2e/STEP07A.md) own its configuration and evidence.
A neutral fresh application installs the unchanged private package on the fixed
Craft source/asset pins without a content schema or package UI. Project-owned
routes, controller, public root, Vue page/resolver and client/SSR builds prove
direct HTML and Inertia navigation.

The project explicitly selects native fallback views when its opt-in is off or
Craft's plugin registry reports disabled. Native enable/disable commands and
fresh boots exercise that contract with cached routes, while representative
CP/action/API/feed endpoints and unknown URLs retain native handling. Installing
the package never implicitly takes over public routes. Composer availability
alone does not follow Craft enablement; both project response selection and
middleware must honor the native registry.

This does not complete full STEP-07/AC-07. Package identity/license/distribution,
upgrades, selected third-party integrations, account defects, public output
caching, general release and production acceptance remain separate. The
Playground remains local/uncommitted, and no native dependency is patched.

### STEP-07B Optional native Contact Form integration

The human selected `craftcms/contact-form` after merged STEP-07A. This independent
bounded integration uses the official 4.x-dev commit
`6f3b6deed57d909ab024190a53d93dedc206e13f` on the unchanged accepted Craft source
and asset pins. The [versioned capability matrix](CONTACT_FORM.md) names exactly
what is exercised and the [report](../reports/e2e/STEP07B.md) records evidence.
A project-owned accessible Vue page submits scalar native fields through the
official Inertia form; the owning plugin retains validation, settings, rate
limits, CSRF/session, flash/redirect and real local mail rendering/handoff.

Contact Form is optional in an isolated consumer, never a dependency of the core
package or other suites. No original Playground activation, real email,
framework/plugin patch, dependency bypass or upgrade is authorized. Nested fields,
enabled attachments, custom events, JSON clients, CP/settings UI and production
mail are not accepted by the scalar proof. A native nested-message 500 remains
an explicitly failing regression; no criterion is waived to make it pass.
Full STEP-07/AC-07 and the existing native account defects remain open.

### STEP-07C Permanent private package identity

The human approved Composer `robuust/craft-inertia`, PHP `Robuust\CraftInertia`,
native handle `inertia` and display name `Inertia`, retaining private distribution
and the proprietary license. The npm harness is `@robuust/craft-inertia` and stays
private. No public tag/release, Packagist/npm publication or repository visibility
change is included. Existing native pins, dependency graph and schema version
remain unchanged.

Integration `config/craft-inertia.php`, `craft-inertia.*` and tag
`craft-inertia-config` preserve `rootView`/`indexingPolicy`; the official adapter
continues to own `config/inertia.php` / `inertia.*` and SSR. All active examples,
fixtures and tooling use the canonical identity. Historical reports and frozen
source locks retain the identity they actually exercised.

The [migration checklist](INSTALLATION.md#migrate-the-legacy-package-identity)
and [bounded evidence](../reports/e2e/STEP07C.md) cover a fresh current installation
and a disposable installed legacy consumer, using native uninstall/install and
Composer/project changes. They prove preserved custom root/indexing configuration,
official SSR config, native/project data and native fallback across enable/disable
and project opt-out with fresh boots/cached routes. No permanent alias or parallel
registry is introduced. Original Playground source, DB/config/env/vendor and
partial STEP-05 stashes stay untouched; mechanical source patches stay local.

This closes only the bounded identity/installation-migration subset. Four native
account regressions and Contact Form N01 remain open real failures. Full STEP-05,
full AC-06, full AC-07, general upgrade support, production and release acceptance
are incomplete. The coordinator independently reviews the stable result before
merge and owns the next task.

## Completion criteria

The plugin is ready to replace a site's frontend only when every required
capability used by that site is implemented and verified, including its selected
third-party integrations. The three-page demonstration alone does not meet that
threshold. A general release needs a documented support matrix for specific
Craft, Laravel, Inertia and Vue versions; alpha API compatibility must be checked
on upgrades.
