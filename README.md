# Craft Inertia

Private Craft CMS 6 plugin for a project-owned Inertia/Vue public frontend.
Craft owns content, native URLs, sites, publication rules and preview authority.
The plugin connects explicitly selected pages to the official Inertia adapter.
Composer identity is `robuust/craft-inertia`, PHP namespace
`Robuust\CraftInertia`, native handle `inertia`; license is proprietary.
No public package release is implied by the local `0.1.0` development version.

## Install

The current target is PHP 8.5, official Craft `6.0.0-beta.1` and
`inertiajs/inertia-laravel` 3.7. Use an authenticated private Composer source.
For local development, a consuming project's path repository can use:

```json
{
  "repositories": [{
    "type": "path",
    "url": "../craft-inertia",
    "options": {"symlink": true, "versions": {"robuust/craft-inertia": "0.1.0"}}
  }],
  "require": {"robuust/craft-inertia": "0.1.0"}
}
```

```sh
composer update robuust/craft-inertia --no-interaction
php artisan craft:plugin:install inertia --no-interaction
php artisan vendor:publish --tag=craft-inertia-config --no-interaction
```

Craft discovers the plugin provider. Supply your normal public Blade root,
Vue page resolver, layout, Vite client/SSR build and optional SSR service. Use a
public view namespace such as `frontend::app` to distinguish it from Craft's CP
root. The plugin publishes configuration, not a design or frontend bundle.

## Native entry templates

Keep the section's native URI and select a Vue component in its Template:

| Content | Native URI | Template |
| --- | --- | --- |
| Homepage single | `__home__` | `inertia:Site/Home` |
| Articles | `articles/{slug}` | `inertia:Site/Articles/Show` |
| Structure entry | Native nested URI | `inertia:Site/Places/Show` |

`inertia:Site/Articles/Show` resolves through the project's normal resolver,
for example `resources/js/pages/Site/Articles/Show.vue`. Select public fields in
`config/craft-inertia.php`:

```php
return [
    'rootView' => 'frontend::app',
    'indexingPolicy' => null,
    'pages' => [
        'Site/Articles/Show' => ['fields' => ['summary', 'body', 'heroImage']],
    ],
];
```

The plugin supplies `entry`, `site`, `preview`, `seo` and official response
protocol props. Selected values keep native handles under `entry.fields`;
no fields are implicit. Core scalars, dates, choices, sanitized Markdown,
assets/transforms/srcset, related entries and nested Matrix content have bounded
selection. [CONTENT.md](docs/CONTENT.md) defines the exact shapes and a small
explicit custom-field callback.

Ordinary mapped entry pages need no project entry controller, copied Laravel
entry route, custom PHP renderer or per-page SSR middleware. The
[minimal consumer](tests/e2e/entry-templates) demonstrates two independent schemas,
a homepage and native structure URL. Projects still own Vue/layout/build, field
selection, custom serializers, search/navigation queries and business rules.

The exact `inertia:` prefix is opt-in. Explicit native route/controller
destinations take precedence. Twig/Blade, dynamic view routes, CP, actions,
API/feed endpoints and unknown paths retain native handling. No catch-all is
registered. An already configured project route remains ordinary Laravel code.

## Bespoke project routes

Existing manual integrations remain supported. Apply
`Robuust\CraftInertia\Http\HandleInertiaRequests` only to selected page routes,
after `web` and `craft`; render explicit project props through `Inertia::render()`.
For native destinations, consume Craft's `#[CurrentElement] Entry` rather than
requerying a slug/ID. Registered named/controller routes must match native
URI/domain/method and retain their own middleware. This path suits overviews,
search, special queries and private/business pages.

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
clean consumer verification and the historical explicit-route lifecycle proof.
[FORMS.md](docs/FORMS.md) covers native mutations/files, and
[CONTACT_FORM.md](docs/CONTACT_FORM.md) records bounded optional compatibility.

## Verification scope

[Fixed metadata](tests/e2e/official-craft.json) pins genuine beta.1 CMS/assets
source and dist manifests. Disposable consumers resolve those dependencies;
the original Playground manifest/lock and local work are preserved. The previous
official development baseline and STEP reports remain historical evidence;
they do not establish released alpha.20 compatibility.

Use Node 24 explicitly. `npm run typecheck`, `npm run format:check`, Composer
validation and PHP syntax checks cover tooling/source. Authorized integration
checks use the isolated PHP/Playwright harnesses documented in
[INSTALLATION.md](docs/INSTALLATION.md). Their bootstrap guards isolate database,
cache, sessions and mail. Local HTTP servers disable OPcache for rapid fixture
config changes. Keep exact locks, native hashes, build manifests and reports.

Four native account regressions and Contact Form's N01 nested-message failure
remain separate open failures. The local unaccepted account adapter is excluded.
Full STEP-05, full AC-06/AC-07, general compatibility, release and production
acceptance remain incomplete. No PHP coverage percentage is claimed without a
compatible driver. [FRONTEND.md](docs/FRONTEND.md) owns the agreed requirements.
