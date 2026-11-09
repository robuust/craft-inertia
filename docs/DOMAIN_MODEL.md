# Craft Inertia domain and response contracts

This model describes ownership and data crossing the Craft-to-Vue boundary.
It does not introduce a second content store or propose database migrations.
Terms are defined in [CONTEXT.md](../CONTEXT.md); product requirements and
acceptance criteria remain in [FRONTEND.md](FRONTEND.md).

**Basis:** plugin commit `aa361006dc985fa7c6343c8c84372adf58f2b7e0`, Playground
commit `f7dd95da555f704d49aa590f7fa60bf7d24f1731`, installed Craft
`6.0.0-alpha.20`, inspected on 2026-11-09. Current contracts are distinguished
from proposed contracts below. Primary source locations are indexed in
[PROJECT.md](PROJECT.md#source-register).

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
| Page selection            | Chooses a project page and its public projection for a resolved request.  | A project page may represent an entry or an overview with no matched element. Final configuration API is a STEP-01 implementation decision. |
| Public projection         | Explicit values the project makes available to the page.                  | Preserve existing canonical names where applicable. Do not dump an element, user, field layout or query object into props.                  |
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
a conservative private/no-store policy; production caching belongs to STEP-06:

1. Craft resolves site context, normal session authority and any valid preview
   token before the public page consumes content.
2. Native route configuration selects a project destination. For element pages,
   the destination consumes Craft's matched element; an overview uses a
   project-owned query. Explicit route configuration determines which requests
   opt into Inertia.
3. Project PHP code projects allowed content into page props. It uses the
   existing query, relation, field, transform and authorization APIs.
4. The official Inertia adapter supplies initial HTML or an Inertia page JSON
   response. The project owns the public root view, page resolver and client/SSR
   builds. Craft's CP uses its own root view, props and assets.
5. Response policies apply to both HTML and page JSON. Preview and private
   responses remain outside shared public caches. Public cache keys distinguish
   site, URL/query and response representation; content changes invalidate
   affected output.

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

Preview markers can be removed during Craft's internal request preparation.
Consume the server-resolved element and context rather than relying on query
markers. STEP-01 exercises basic canonical URL token/privacy boundaries;
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
only an error bag can therefore misreport failure as success. STEP-04 owns this
transport contract; STEP-05 applies it to account flows.

## Model decisions still needed

| Decision                                  | Owning step and effect                                                                                            |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Minimal page/root/asset configuration API | Settled in STEP-01: native destinations, project controllers/pages/build and opt-in middleware with a configurable root view. |
| Public projection extension conventions   | STEP-02; demonstrate distinct schemas before extracting shared field abstractions.                                |
| Site and preview public context shape     | STEP-03; preserve native identity and expose only page-required context.                                          |
| Supported native action contracts         | STEP-04 and STEP-05; inspect responses rather than duplicate validation.                                          |
| Public SSR/cache policy configuration     | STEP-06; establish HTML/JSON variation, private isolation and invalidation.                                       |
| Initial third-party support matrix        | STEP-07; product selection remains open and does not block the core first step.                                   |

## STEP-01 integration contract

`Robuust\CraftInertiaPoc\Http\HandleInertiaRequests` is selected explicitly on
project GET/HEAD page routes. `inertia-poc.rootView` names the project Blade root
(default `app`); the project's Vite manifest supplies asset versioning. The
package registers no routes, page resolver, shared schema, preview targets or
frontend assets. Intentional project shared props remain intact.

Craft's configured native named/controller destinations and `CurrentElement`
injection supply entries with Craft-owned site, publication and preview state.
The project chooses page names and explicit projections. Dynamic project-config
view routes retain Craft alpha.20 precedence on identical route signatures;
projects keep those signatures disjoint from Inertia page routes. See the
[installation and route policy](../README.md).
