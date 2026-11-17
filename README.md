# Craft Inertia

Craft CMS 6 plugin for a project-owned Inertia/Vue public frontend.
Craft owns content, native URLs, sites, publication rules and preview authority.
The plugin connects explicitly selected pages to the official Inertia adapter.
Composer identity is `robuust/craft-inertia`, PHP namespace
`Robuust\CraftInertia`, native handle `inertia`; license is proprietary.
Source and Composer downloads are public; usage remains subject to the
[proprietary license](LICENSE.md). This is a beta integration for Craft 6 beta.

## How it works

For a homepage mapped to `inertia:Site/Home`, the request flows through:

```mermaid
flowchart TD
    A["Browser requests /"] --> B["Craft resolves the homepage entry and current site"]
    B --> C["Template: inertia:Site/Home"]
    C --> D["Plugin projects the entry and its fields"]
    D --> E["Official Inertia adapter"]
    E -->|"First visit"| F["HTML with SSR when configured and permitted"]
    E -->|"Navigation via an Inertia Link"| G["JSON with component name and page props"]
    F --> H["Site/Home.vue renders the page"]
    G --> H
```

1. Craft resolves the requested URL, current site and entry. It applies its normal
   publication and preview rules before the plugin selects a renderer.
2. The plugin listens to Craft's native entry-routing event. A section template
   such as `inertia:Site/Home` selects the Vue component `Site/Home`. Explicit
   route/controller destinations keep precedence.
3. The plugin projects the actual matched entry into JSON-compatible page props.
   By default it includes every custom field in the entry's field layout under
   `entry.fields.<handle>`, including related entries and Matrix content.
   Projects can configure recursive public field selection and responsive images.
   Authorized previews retain their matched draft/revision identity.
4. The plugin renders through the official Inertia Laravel adapter, using an
   isolated public response factory. It excludes CP/global shares and applies
   the configured root view, asset version, flash/error handling and response
   privacy policy.
5. A first visit receives an HTML document and page data. When guest SSR is
   configured and permitted, that HTML already contains the rendered Vue page.
   Navigation through an Inertia `Link` receives page JSON and updates Vue in
   the browser. Vue owns the page layout, components and interactions.

The main integration points are [EntryTemplates](src/Routing/EntryTemplates.php),
[EntryProjection](src/Content/EntryProjection.php) and
[EntryController](src/Http/Controllers/EntryController.php), with the shared
[PublicRenderer](src/Http/PublicRenderer.php) for explicit project controllers.

| Owner | Responsibilities |
| --- | --- |
| Craft | Fields, entry types, sections, native URLs, sites, publication, preview identity and authorization. |
| This plugin | Opt-in entry rendering, field/link/image projection, generic Head rendering, public response isolation and Craft/Inertia policy. |
| Official Inertia adapters | HTML/page-JSON negotiation, client navigation, asset-version handling and SSR transport. |
| Consuming project | Vue pages and design, public Blade root, Vite builds, website queries, SEO content and runtime/deployment configuration. |

## Install

The package requires PHP 8.5, Craft `^6.0.0-beta.1`, Laravel `^13.38` and
`inertiajs/inertia-laravel` `^3.7`. Install it from
[Packagist](https://packagist.org/packages/robuust/craft-inertia) in an existing
Craft 6 project that permits the Craft beta dependencies:

```sh
composer require 'robuust/craft-inertia:^0.1.0@beta' --with-all-dependencies --no-interaction
php artisan craft:plugin:install inertia --no-interaction
php artisan vendor:publish --tag=craft-inertia-config --no-interaction
```

No custom VCS repository or GitHub credentials are required. When migrating from
a private VCS source, remove that repository entry and update the package lock.
Craft discovers the plugin provider automatically. The package publishes its
integration configuration; the consuming project supplies the frontend files.

For local package development, a consuming project's path repository can use:

```json
{
  "repositories": [{
    "type": "path",
    "url": "../craft-inertia",
    "options": {"symlink": true, "versions": {"robuust/craft-inertia": "0.1.0-beta.1"}}
  }],
  "require": {"robuust/craft-inertia": "^0.1.0@beta"}
}
```

The local path must be available wherever Composer runs. Use the published
package for CI and deployment environments that do not have that checkout.

## Configure the consuming project

The one-time host setup consists of:

| Location | Project configuration |
| --- | --- |
| `config/craft-inertia.php` | Select the public root view, for example `frontend::app`, and an explicit indexing policy when required. |
| Project service provider | Register the public view namespace and exclude native CP/action paths from the public SSR resolver. |
| `resources/views/app.blade.php` | Provide the HTML shell, Vite assets, `<x-craft-inertia::head :page="$page" />` and `<x-inertia::app />`. |
| `resources/js/app.js` | Bootstrap the official Vue adapter with `createInertiaApp()`. |
| `resources/js/pages/` | Supply the Vue components selected by section templates. |
| `vite.config.js` and `package.json` | Configure Vue and the official Inertia Vite plugin, and build the client and SSR bundles. |
| Project runtime | Configure SSR connectivity, a production SSR process when enabled, persistent asset storage and worker lifecycle. |

Set the published configuration to the project's public namespace:

```php
return [
    'rootView' => 'frontend::app',
    'indexingPolicy' => null,
];
```

For example, register the namespace and SSR exclusions in the project service
provider's `boot()` method. When `cpTrigger` is `null`, first configure Craft's
`baseCpUrl` with the dedicated control-panel origin, such as
`https://cms.example.test`. This lets the same setup distinguish CP requests
from the public website without a global `/*` exclusion:

```php
use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Url;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;

// Inside the project's service provider boot() method:
View::addNamespace('frontend', resource_path('views'));
$cpUrl = rtrim(Url::cpUrl(), '/');
$actionTrigger = trim(Cms::config()->actionTrigger, '/');
Inertia::withoutSsr([
    $cpUrl,
    $cpUrl.'?*',
    $cpUrl.'/*',
    $actionTrigger,
    $actionTrigger.'/*',
]);
```

The namespace separates the public root from Craft's CP root. Keep the plugin's
own provider under native Craft discovery; do not register it a second time.

The official `@inertiajs/vite` plugin can resolve pages and handle development
SSR from the normal application entry point. Cross-container development must
make its SSR endpoint reachable through the official `inertia.ssr.hot_url`
setting. Production uses the built SSR bundle and a separately supervised Node
process. SSR/protocol settings belong to `inertia.*`; integration settings belong
to `craft-inertia.*`. See [INSTALLATION.md](docs/INSTALLATION.md) for the complete
host setup and [the official SSR guide](https://inertiajs.com/docs/v3/advanced/server-side-rendering).

## Native entry templates

Keep the section's native URI and select a Vue component in its Template:

| Content | Native URI | Template |
| --- | --- | --- |
| Homepage single | `__home__` | `inertia:Site/Home` |
| Articles | `articles/{slug}` | `inertia:pages/articles/[slug]` |
| Structure entry | Native nested URI | `inertia:Site/Places/Show` |

`inertia:Site/Articles/Show` resolves through the project's normal resolver,
for example `resources/js/pages/Site/Articles/Show.vue`. All fields from the
entry's field layout are automatically supplied under their native handles:

```vue
<p>{{ entry.fields.summary }}</p>
<img v-for="image in entry.fields.heroImage" :key="image.id" :src="image.url" :alt="image.alt" />
```

No field-selection configuration or admin selector is required. Craft's native
project-config YAML already stores the section template and entry-type layout.
The plugin supplies `entry`, `site`, `preview`, `head` and official protocol props.
It automatically includes nested Matrix and related entry fields, with bounded
circular-reference handling. Assets use their original native URLs; other field
types use their own native serializers. [CONTENT.md](docs/CONTENT.md) defines
the exact shapes and boundaries. Configure `entryFields` by entry-type handle
to preserve a public whitelist for root and related entries. Without a matching
rule, all attached fields, including internal notes and fields hidden by editor
layout conditions, remain part of page data. The old `pages[component].fields`
setting is ignored; it is not the current selection mechanism.

| Page prop | Contents |
| --- | --- |
| `entry` | Native identity, title, URL, entry type and selected layout fields under `fields` (all without a matching rule). |
| `site` | Current site identity, language and base URL. |
| `preview` | Whether Craft supplied a preview entry. |
| `head` | Generic title/meta/link/JSON-LD and effective robots policy; no third-party plugin coupling. |
| Protocol props | Official adapter errors, flash and other protocol state. |

Assets retain list cardinality, even when a field allows one image. They include
the original URL, dimensions and alt text, with `srcset: null`. Select responsive
`variants` declaratively through `fieldImageProfiles`; the site chooses its image
profiles. Links use publication-aware URLs by default. Native HTML field data
uses its rendered representation. Table rows retain native column keys such as
`col1` and `col2`. The generic `head` callback accepts project-calculated metadata;
packaged [Vue/Blade Head components](docs/HEAD.md) render it with consistent
robots policy, including without SSR. The plugin detects or imports no
third-party metadata plugin.

Ordinary mapped entry pages need no project entry controller, copied Laravel
entry route, custom PHP renderer or per-page SSR middleware. Projects still own
Vue/layout/build, search/navigation queries and business rules.

The exact `inertia:` prefix is opt-in. Explicit native route/controller
destinations take precedence. Twig/Blade, dynamic view routes, CP, actions,
API/feed endpoints and unknown paths retain native handling. No catch-all is
registered. An already configured project route remains ordinary Laravel code.

## Bespoke project routes

Use `Robuust\CraftInertia\Http\PublicRenderer` for project controllers that select
their own props. It runs the same isolated response factory, SSR eligibility and
public protocol policy as the native entry renderer. Resolve props inside its
callback and use the supplied robots directive in project metadata:

```php
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\CurrentElement;
use Illuminate\Http\Request;
use Robuust\CraftInertia\Http\PublicRenderer;
use Symfony\Component\HttpFoundation\Response;

public function __invoke(Request $request, #[CurrentElement] Entry $entry): Response
{
    return app(PublicRenderer::class)->render(
        $request,
        'pages/work/[slug]',
        fn (string $robots): array => $this->pageProps($entry, $robots),
        $entry,
    );
}
```

The project owns `pageProps()`, including its selected content and SEO shape.
This API adds no automatic `entry`, `site`, `preview` or `head` props. The native
entry renderer supplies its standard projection. Components passed directly to
`PublicRenderer` use the host resolver; `inertia:` templates also accept bracketed
component segments such as `pages/work/[slug]`. Craft's actual matched live or
authorized preview entry is passed without requerying a canonical ID or slug. Missing entry context
and non-200 page statuses always disable public SSR and indexing.

The optional `publicPagePolicy(Request, Entry): bool` config callback can restrict
eligible pages or sections; it cannot allow authentication, preview, token or
form-state contexts. Existing `indexingPolicy(Request): bool` remains default
deny, and Craft's `disallowRobots` overrides it. Projects own their content
selection and authorization. See [RENDERING.md](docs/RENDERING.md#shared-public-renderer)
for controller, policy and error-response migration examples.

Keep `web` and `craft` context resolution before the controller. The shared
renderer runs the public Inertia middleware itself; do not wrap it in a second
Inertia protocol middleware. Native registered named/controller routes still
match their own URI/domain/method and retain other middleware. No catch-all or
routing listener is added for explicit controllers.

Existing manual integrations using `HandleInertiaRequests` and `Inertia::render()`
remain supported. Those integrations retain responsibility for their own share
isolation, guest SSR and indexing eligibility. The shared renderer centralizes
that boundary for selected project props and custom error pages.

Project-wide navigation/settings queries use the `publicProps` callback; global
`Inertia::share()` values remain excluded. Field selection, Assets profile
selection and generic Head metadata have declarative configuration, so ordinary
entry pages need no project controller or field serializer. See
[public page extensions](docs/EXTENDING.md).

## Rendering, privacy and lifecycle

Integration config belongs to `craft-inertia.*`. Official adapter SSR/protocol
config belongs only to `inertia.*`. Mapped pages use the project root and Vite
manifest version, private/no-store headers and `Vary: X-Inertia`. Indexing defaults
to deny; an explicit callable must return `true`, and Craft `disallowRobots` wins.
Private/auth/token/preview/form-state requests remain non-indexable and do not
reach SSR. Public guest SSR respects official configuration and host callbacks.
CP/global shared props are excluded from mapped pages; the host factory/gateway
are restored afterward. See [RENDERING.md](docs/RENDERING.md).

Native plugin disable/enable removes/restores the listener on a new boot,
including with cached routes. Restart long-lived workers after lifecycle changes.
The plugin does not rewrite section templates, DB state or project YAML.
Disabling an `inertia:`-only template leaves normal native template interpretation;
a working Twig/Blade fallback must be deliberately configured and retained.
There is no automatic reconstruction of a previous Twig design.

[INSTALLATION.md](docs/INSTALLATION.md) covers current setup, local-only migration,
and native lifecycle/fallback behavior.
[FORMS.md](docs/FORMS.md) covers the generic `NativeForm::props($action, $redirect)`
helper and native mutations/files. [CONTACT_FORM.md](docs/CONTACT_FORM.md) shows
Contact Form as an optional project integration, using native validation events,
its existing action and the shared errors/flash bridge.

## Planned extensions

These proposals extend the current integration. They are not implemented
capabilities; the linked issues define their scope and acceptance criteria.

| Issue | Extension |
| --- | --- |
| [#13](https://github.com/robuust/craft-inertia/issues/13) | Safe frontend scaffolding and installation diagnostics. |
| [#16](https://github.com/robuust/craft-inertia/issues/16) | TypeScript page props generated from Craft field layouts. |
| [#17](https://github.com/robuust/craft-inertia/issues/17) | A versioned compatibility and production acceptance matrix. |

## Verification scope

This plugin contains no automated tests, fixtures or application harness. Its
packaged Vue Head component uses the consuming project's frontend toolchain;
the complete Vue application remains project-owned.
Use Composer validation and PHP syntax checks for the package, and verify native
URLs, complete field data, preview identity, SSR/hydration, forms, privacy and
fallback in the actual consuming project with its own locked dependencies.

Earlier development reports and test sources remain available in Git history.
They describe their original versions and bounded outcomes; they do not establish
current compatibility or production acceptance.

Four native account regressions remain separate open failures. Contact Form's
historical N01 nested-message failure is fixed in the inspected 4.0.0 source;
runtime acceptance of that release remains unverified. The local unaccepted
account adapter is excluded.
Full STEP-05, full AC-06/AC-07, general compatibility, release and production
acceptance remain incomplete. No PHP coverage percentage is claimed without a
compatible driver. [FRONTEND.md](docs/FRONTEND.md) owns the agreed requirements.

Generic public project props, serializers and image profiles are
documented in [Public page extensions](docs/EXTENDING.md).
