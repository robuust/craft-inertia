# Reusable Craft frontend plugin

The plugin must let a Craft site implement its complete public frontend with
Inertia and Vue. Each project supplies its own pages, layouts and presentation.
Craft remains responsible for content, URLs, publication rules, sessions,
permissions and preview authorization.

This is the agreed product direction following the initial proof of concept.
The capabilities below are delivery requirements, not claims about the current
package. The current implementation still serves a fixed article demonstration
under `/inertia-poc`; its existing [E2E report](../reports/e2e/REPORT.md) covers
that demonstration only.

## Ownership

| Owner         | Responsibility                                                                                                                                                               |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Craft         | Content models and queries, element URLs, site and locale resolution, publication status, drafts, revisions, preview tokens, accounts and permissions.                       |
| This plugin   | Connect Craft site requests and application responses to the official Inertia adapter; provide configuration, public page context, response policies and integration points. |
| Each project  | Vue pages, layouts, navigation, styling and accessibility; PHP queries and explicit public data projections for its content and business rules.                              |
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

| Capability               | Required observable behavior                                                                                                                                                                                   | Current proof of concept                                                                         |
| ------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Native element URLs      | A section's normal entry URL opens the configured Vue page. Craft still resolves the element and applies publication rules. Homepage entries, singles, structures and nested URLs work.                        | Separate numeric demo URLs only.                                                                 |
| Other site routes        | Overview, search and other project pages can use named Laravel routes and Craft dynamic route configuration. Static pages, feeds and non-HTML endpoints keep their appropriate response type.                  | One fixed overview.                                                                              |
| Content                  | Explicit public props support core field values, relations, globals, taxonomy, rich text and nested Matrix content. Project or plugin field types have a supported extension point.                            | Title, date and two optional plain text fields.                                                  |
| Assets                   | Craft generates asset URLs, transforms and responsive image data. Authorized uploads and downloads retain native validation and access rules.                                                                  | No asset integration.                                                                            |
| Queries                  | Search, filters, sorting and pagination use Craft queries and shareable URLs. Projects choose query scope and public projections.                                                                              | Latest 20 live entries.                                                                          |
| Multisite                | Language, domain, subdirectory, site identity and translated URLs follow Craft's current site. Cross-site navigation uses the appropriate URL and full-page navigation when required.                          | One site at the origin root.                                                                     |
| Preview                  | Native draft, revision and provisional-draft preview displays the authorized element at its site URL, refreshes after autosave and remains separate from public visits.                                        | Demo preview works functionally; one native Craft CP JavaScript error remains in the E2E result. |
| Forms and actions        | Mutations preserve CSRF, validation errors, old input, error bags, flash messages, redirects and upload behavior through the normal Inertia form flow.                                                         | Not implemented.                                                                                 |
| Accounts and permissions | Login, logout, registration, activation, password reset, protected pages and applicable account challenges use Craft's existing authentication and authorization. Public props expose only intended user data. | Host authentication is excluded from demo props.                                                 |
| Errors and redirects     | Missing or unavailable content, authorization errors, expired sessions and maintenance have correct HTTP status and Vue presentation where appropriate. Existing redirects keep their destination and status.  | A demo entry 404 page.                                                                           |
| SEO and rendering        | Projects can render initial HTML through SSR and supply titles, metadata, canonical URLs, hreflang and structured data. Public pages are indexable according to site policy.                                   | Client rendering; all demo responses are noindex.                                                |
| Cache and deployment     | HTML and Inertia JSON have correct variation and asset versions. Preview and private responses cannot enter public caches. Public caching and invalidation respect Craft dependencies and publication changes. | All demo responses are private/no-store.                                                         |
| Other plugins            | Documented integrations reuse the owning plugin's APIs, actions and permissions. Compatibility names the plugin, version and supported features.                                                               | No integrations validated.                                                                       |

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

Craft `6.0.0-alpha.20` exposes the [`SetRoute` event](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Element/Events/SetRoute.php)
and [`ControllerRoute`](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Route/ControllerRoute.php).
These provide a concrete starting point for mapped element responses. Native
controller or named-route configuration is also available in Craft's
[`ElementRoute`](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Route/ElementRoute.php).
The first slice must establish route precedence, including explicit project
routes and homepage handling, before choosing the final configuration API.

Use the official [Inertia Laravel adapter](https://inertiajs.com/docs/v3/installation/server-side-setup)
for response negotiation, redirects, shared data and asset versioning. A Vue
page is an HTTP response, not a replacement string renderer: Craft's
[`TemplateRendererInterface`](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/View/Contracts/TemplateRendererInterface.php)
returns strings and cannot by itself supply the complete Inertia protocol.

The project owns one public Vue application and its Vite and SSR builds. The
package must coexist with Craft's separate control-panel frontend. Public page
middleware must not inherit CP props or a CP root view. The current blanket
`flushShared()`, SSR exclusions and noindex/no-store policies are specific to
the demonstration and must be replaced with deliberate public response policies.

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

### 1 Native URLs and project pages

Introduce opt-in element-to-page configuration and explicit PHP data providers.
Use the project's public root view, page resolver and asset build. Keep unmapped
requests under their existing route handling. Extract the article demonstration
from the reusable runtime once the project integration can replace it.

Acceptance: two sections with different fields render different project Vue
pages at their Craft URLs; a homepage single and an overview work without the
demo prefix; direct and Inertia visits agree; unavailable entries remain
unavailable; explicit route precedence is documented; CP, actions, assets and
GraphQL retain their existing handling. A second example configuration uses
different section and field handles without modifying package source.

### 2 Content and queries

Provide the smallest shared conventions needed for public content and asset
projections. Add Playground examples for relations, rich text, nested Matrix,
globals, taxonomy, transforms, search, sorting and pagination. Keep schema and
query choices in the project.

Acceptance: the same package supports two distinct content schemas; query
parameters survive navigation and browser history; nested content and missing
optional fields render correctly without exposing internal element data.

### 3 Native preview and multisite

Integrate preview with the mapped canonical pages. Carry Craft's current site
through routing, props, links and response policies. Cover published, named and
provisional drafts, unpublished entries and revisions as supported by Craft.

Acceptance: autosave updates the native preview while an independent public
visit sees published content; invalid or unrelated tokens reveal no draft data;
language, domain and subdirectory sites resolve correctly. Track the existing
native CP error separately from the plugin's preview behavior.

### 4 Forms and file operations

Connect native and project actions to Inertia forms. Define error, flash,
redirect and JSON-action handling from actual Craft contracts. Include uploads
and protected downloads.

Acceptance: valid and invalid submissions, CSRF failures, multiple error bags,
file validation and post-submit redirects work without duplicate validation or
client-side copies of server state.

### 5 Accounts and access

Provide integration examples for native account flows and protected pages.
Share a deliberate public user projection and preserve Craft's session guard,
authorization and redirect behavior.

Acceptance: login, logout, registration, activation, password reset, expired
sessions and applicable account challenges work through the Vue frontend;
guests and unauthorized users cannot access protected props or operations.

### 6 Rendering and response policies

Add public SSR, metadata, canonical and alternate links, error pages, maintenance
behavior, cache variation and invalidation. Separate public, authenticated and
preview response policies. Support deploy-time asset version changes.

Acceptance: an initial public request contains meaningful HTML and metadata;
direct visits and Inertia navigation preserve status and redirect behavior;
caches distinguish HTML from page JSON and never mix sites, users or previews;
publishing or changing content invalidates affected output.

### 7 Reusable installation and plugin compatibility

Document a clean installation, project configuration, local development,
production builds, SSR deployment, upgrade requirements and migration from
Twig. Select third-party integrations explicitly and publish a versioned
capability matrix for them.

Acceptance: the package installs in a clean supported Craft application and
the Playground without package source changes; no demo schema or UI is
required; disabling it restores the project's configured native handling;
each advertised third-party integration has its own acceptance evidence.

## Completion criteria

The plugin is ready to replace a site's frontend only when every required
capability used by that site is implemented and verified, including its selected
third-party integrations. The three-page demonstration alone does not meet that
threshold. A general release needs a documented support matrix for specific
Craft, Laravel, Inertia and Vue versions; alpha API compatibility must be checked
on upgrades.
