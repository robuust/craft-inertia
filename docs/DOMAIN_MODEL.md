# Craft Inertia domain and response contracts

This model describes ownership and data crossing the Craft-to-Vue boundary.
It does not introduce a second content store or propose database migrations.
Terms are defined in [CONTEXT.md](../CONTEXT.md); product requirements and
acceptance criteria remain in [FRONTEND.md](FRONTEND.md).

**Current native-template contract:** approved 2026-11-11, targeting official
Craft `6.0.0-beta.1` source `05ce9b81be3704fb27da3a5a6670accfa49ec9a9`.
The earlier initialization inspected plugin `aa36100`, local Playground
`f7dd95d` and alpha.20; historical contracts/reports retain their original scope.
[CONTENT.md](CONTENT.md) owns the selected-field shape.

## Actors and authority

| Actor             | Intended journey                                           | Authority owner                                                  |
| ----------------- | ---------------------------------------------------------- | ---------------------------------------------------------------- |
| Visitor           | Browse, search and submit public forms.                    | Craft publication rules and each project's action permissions.   |
| Signed-in visitor | Use protected pages and account operations.                | Craft's configured guard, session and authorization.             |
| Editor            | Inspect another content version and publish through Craft. | Craft CP permissions, draft lifecycle and preview tokens.        |
| Project developer | Select pages, public data and presentation.                | Project configuration and PHP/Vue source.                        |
| Operator          | Install, build, configure and deploy the integration.      | Supported package versions and project deployment configuration. |

An editor session is not a reason to share CP props with a public page. A preview
target is a destination, not an authorization grant. A visitor who knows an entry
ID, site ID, draft ID or preview marker has not thereby obtained access.

## Concepts and relationships

| Concept                   | Identity and relationships                                                | Invariants and owner                                                                                                                        |
| ------------------------- | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Craft site                | Existing Craft site ID/UID; can have its own language, base URL and path. | Resolve through Craft. A language alone does not identify a site.                                                                           |
| Canonical entry           | Existing entry identity; has site variants and can have drafts/revisions. | Craft owns status, dates, structure, fields and URL rules. Canonical identity does not imply public availability.                           |
| Site variant              | Representation of an entry in one configured site.                        | Entry identity and current site jointly determine the content/URL context; do not replace them with a slug alone.                           |
| Preview content           | Version related to a canonical entry and a site.                          | Craft validates authorization and supplies the preview element. Ordinary queries must not inherit another request's preview state.          |
| Page selection            | Chooses a project page and its public projection for a resolved request.  | A project page may represent an entry or an overview with no matched element. Native `inertia:Component` templates use plugin rendering; explicit native destinations retain precedence. |
| Public projection         | Explicitly selected Craft values made public by plugin or project code.                  | Preserve existing canonical names where applicable. Do not dump an element, user, field layout or query object into props.                  |
| Compatibility integration | Named external plugin, version range and supported operations.            | That plugin remains responsible for its calculations, validation and permissions. Installation alone does not establish compatibility.      |

These are logical relationships using Craft's existing model. A project may
choose its own content schema; the reusable plugin must not require demo fields,
sections, entry types or new storage tables.

## Historical demonstration contract

The original demo (before STEP-01) exposed the following values. Its runtime,
routes and package-owned frontend have been removed; the consuming project now
owns the example controllers and pages:

| Value                              | Current representation and meaning                                                                         |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `indexUrl`                         | URL of the fixed demo overview.                                                                            |
| `site.name`, `site.language`       | Current Craft site's display name and language. They are not a complete multisite contract.                |
| `entries[]` / `entry`              | Explicit entry summaries; detail additionally has `body`.                                                  |
| `id`                               | Canonical entry ID, not an arbitrary draft ID.                                                             |
| `title`, `summary`, `body`         | Strings; optional configured summary/body fields produce an empty string when unavailable or not a string. |
| `url`                              | Numeric demo entry URL, not the entry's canonical site URL.                                                |
| `postDate`                         | Date formatted as `Y-m-d`, or null.                                                                        |
| `section.handle`, `section.exists` | Overview's configured section and whether it exists.                                                       |
| `isPreview`                        | Detail's server-supplied preview state.                                                                    |

This was a demo-specific contract. STEP-01 removes the numeric demo routes and
moves the examples into the consuming project. It does not turn these fields
into a universal schema.

## Target request contract

The following responsibilities define the reusable integration;
STEP-01 implements items 1–4 through native destinations, required
`CurrentElement` parameters and opt-in public middleware. Item 5 currently uses
a conservative private/no-store policy; STEP-06A adds guest SSR/errors/asset versions,
STEP-06B adds project indexing/structured data and STEP-06C adds native public
content-data caching. HTTP output caching and production acceptance remain deferred:

1. Craft resolves site context, normal session authority and any valid preview
   token before the public page consumes content.
2. Native route configuration selects a project destination. For element pages,
   the destination consumes Craft's matched element; an overview uses a
   project-owned query. Explicit route configuration determines which requests
   opt into Inertia.
3. For `inertia:` entry templates, plugin code projects safe base context and
   explicitly selected fields using native APIs. Projects select handles and
   own custom serializers and bespoke query/projection behavior.
4. The official Inertia adapter supplies initial HTML or an Inertia page JSON
   response. The project owns the public root view, page resolver and client/SSR
   builds. Craft's CP uses its own root view, props and assets.
5. Response policies apply to both HTML and page JSON. Preview and private
   responses remain outside shared public caches. Public cache keys distinguish
   site, URL/query and response representation; content changes invalidate
   affected output.

STEP-06C caches only explicit project content arrays, keyed by native current
site, normalized filters/page, schema configuration and a projection version.
HTML and page JSON may share that data key because no representation or request
state is stored. SEO/robots/JSON-LD, links, session state, protocol decisions and
errors are rebuilt for each response. Private/preview/token/form-state requests
bypass the data cache. Craft's native dependency collector and dependency-aware
store own invalidation; the project supplies scheduling boundaries for content
that is not yet in the live query. See [RENDERING.md](RENDERING.md).

Native named destinations must match the entry's URI, domain and method.
Registered controller destinations also retain their configured Laravel
middleware. Controller fallback dispatch needs deliberate method and middleware
handling; it is not permission to expose a destination on every request method.

An `isSiteRequest()` check alone is too broad to select public Inertia traffic:
the installed implementation mainly distinguishes CP requests. Actions and
non-page endpoints need explicit exclusion or an opt-in route boundary.

## Preview and publication lifecycle

Craft owns creation, saving, autosave, revision history and publication. The
plugin consumes the resolved version; it must not duplicate those transitions.

| Request state                                    | Required content and response behavior                                                             |
| ------------------------------------------------ | -------------------------------------------------------------------------------------------------- |
| Ordinary visit                                   | Only content available under Craft's publication and permission rules.                             |
| Authorized named/provisional/unpublished preview | The version authorized by Craft for the canonical entry and current site.                          |
| Invalid, expired or unrelated token              | No additional access to draft content.                                                             |
| Autosave                                         | The existing Craft preview refresh mechanism updates the Vue page.                                 |
| Publish                                          | Subsequent ordinary visits use the newly public content and affected cached output is invalidated. |

Native request preparation removes the token query key. Released alpha.20
also removed preview markers; the approved pinned official 6.x source preserves
them. Consume the server-resolved element and context rather than relying on
marker presence. STEP-01 exercises basic canonical URL token/privacy boundaries;
complete editor preview, revisions and multisite acceptance remain STEP-03.
The original saved E2E run covers the numeric demonstration.

## Mutations and failures

Craft/project controllers remain the owners of CSRF, validation, account
challenges, file limits, authorization and domain changes. The integration must
preserve successful redirects, field errors, error bags, messages and old input
where the native action supplies them.

Inspect each native action before adapting it. JSON-expecting requests can
receive JSON instead of session redirects. A failed action can also carry only
a flash message/error code without field errors. A form integration that checks
only an error bag can therefore misreport failure as success. STEP-04 implements this
transport contract in [FORMS.md](FORMS.md); STEP-05 applies it to account flows.

## Model decisions still needed

| Decision                                  | Owning step and effect                                                                                            |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Minimal page/root/asset configuration API | Settled in STEP-01: native destinations, project controllers/pages/build and opt-in middleware with a configurable root view. |
| Public projection extension conventions   | Settled in STEP-02: project-owned typed PHP methods, explicit arrays and native query/field APIs; see CONTENT.md.                                |
| Site and preview public context shape     | STEP-03: project-owned site identity, native URLs and public translation links; matched element `previewing` supplies version context. See CONTENT.md. |
| Supported native action contracts         | STEP-04 covers the operations in FORMS.md; STEP-05 adds account contracts.                                          |
| Public SSR/cache policy configuration     | STEP-06; establish HTML/JSON variation, private isolation and invalidation.                                       |
| Initial third-party support matrix        | STEP-07; product selection remains open and does not block the core first step.                                   |

## Historical STEP-01 manual integration contract

`Robuust\CraftInertia\Http\HandleInertiaRequests` is selected explicitly on
project GET/HEAD page routes. `craft-inertia.rootView` names the project Blade root
(default `app`); the project's Vite manifest supplies asset versioning. The
package registers no routes, page resolver, shared schema, preview targets or
frontend assets. Intentional project shared props remain intact.

Craft's configured native named/controller destinations and `CurrentElement`
injection supply entries with Craft-owned site, publication and preview state.
The project chooses page names and explicit projections. Dynamic project-config
view routes retain Craft alpha.20 precedence on identical route signatures;
projects keep those signatures disjoint from Inertia page routes. See the
[installation and route policy](../README.md).

## Historical STEP-02 content contract

The STEP-02 project example retained STEP-01 field names and added explicit
optional content, globals, filter and pagination values in the consuming project.
The package remains schema-free. Current generic selected-field props are defined
separately in [CONTENT.md](CONTENT.md); they retain native handles. Native Markdown is the covered rich-text variant;
native singles and Entries relations supply alpha.20 globals and taxonomy.
