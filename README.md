# Craft 6 public Inertia frontend

An opt-in public frontend integration for Craft CMS 6 and the official Inertia
Laravel adapter. Craft resolves sites, element URLs, publication state and
preview authorization. The consuming project owns PHP controllers, explicit
page props, Vue pages, the public Blade root and its Vite build.

STEP-01 replaces the original fixed article demo. The package registers no
public routes, pages, preview targets or asset bundle, and requires no particular
section or field handles. Existing `/inertia-poc` and numeric demo URLs are
removed. The examples now live in the consuming Craft 6 Playground application.

This remains an experimental integration, tested with Craft `6.0.0-alpha.20`,
Laravel `13.40.0`, `inertiajs/inertia-laravel` `3.7.0` and `@inertiajs/vue3`
`3.11.0`. The [scope and roadmap](docs/FRONTEND.md),
[initialization](docs/PROJECT.md) and [domain contracts](docs/DOMAIN_MODEL.md)
distinguish this slice from later content, preview, forms, SSR and cache work.

## Install

Make this private package available to Composer. For sibling local checkouts,
add the following repository to the consuming application's `composer.json`:

```json
{
  "type": "path",
  "url": "../craft-inertia",
  "options": {
    "symlink": true,
    "versions": { "robuust/craft-inertia-poc": "0.1.0" }
  }
}
```

Then run from that application:

```sh
composer require robuust/craft-inertia-poc:0.1.0 --no-interaction
php artisan craft:plugin:install inertia-poc --no-interaction
php artisan vendor:publish --tag=inertia-poc-config --no-interaction
```

Craft owns plugin discovery and enablement; do not register its provider again.
There is no package frontend build or asset publication. In containers the
Composer path must be visible inside every consuming PHP service. For example,
a sibling host checkout needs a corresponding `/app/craft-inertia` mount when
the app is at `/app/user` and Composer resolves `../craft-inertia`.

## Opt in project routes

Set the application's `config/inertia-poc.php` to its public root view:

```php
return ['rootView' => 'frontend::app'];
```

The default is `app`. Craft also uses an `app` view for its control panel, so use
a project view namespace to avoid ambiguity. The Playground registers
`View::addNamespace('frontend', resource_path('views'))` and uses its existing
`resources/views/app.blade.php`, `resources/js/app.js` and Vue page resolver.
The [official Inertia setup](https://inertiajs.com/docs/v3/installation/server-side-setup)
owns root rendering and response negotiation.

Apply the public middleware only to selected page routes, after the normal web
middleware. Keep Craft's middleware so site/token handling and response policies
continue to run:

```php
use App\Http\Controllers\SiteController;
use Robuust\CraftInertiaPoc\Http\HandleInertiaRequests;

Route::middleware(['craft', HandleInertiaRequests::class])->group(function (): void {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/articles', [SiteController::class, 'articles'])->name('site.articles.index');
    Route::get('/articles/{slug}', [SiteController::class, 'article'])->name('site.articles.show');
});
```

Use these in the application's `routes/web.php` (which already gets `web`). Do
not attach the public middleware to a catch-all, the CP, actions, GraphQL, feeds
or assets. It bypasses CP and action requests defensively. It preserves
intentional project shared props and the adapter's validation errors; it does
not install Craft CP props on public requests. The asset version comes from the
project's Laravel Vite manifest.

## Native entry destinations and explicit props

Configure a section's site settings with its normal URI format and a **route**
destination, rather than a Twig template. In the Playground example:

| Section | URI format | Native route destination | Vue page |
| --- | --- | --- | --- |
| Homepage single | `__home__` | `home` | `Site/Home` |
| Articles | `articles/{slug}` | `site.articles.show` | `Site/Articles/Show` |
| Projects | `projects/{slug}` | `App\Http\Controllers\SiteController@project` | `Site/Projects/Show` |

The overview is an ordinary project route with a project-owned query. Entry
controllers require Craft's matched element, rather than looking it up from a
numeric ID or slug a second time:

```php
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Route\CurrentElement;
use Inertia\Inertia;
use Inertia\Response;

public function article(#[CurrentElement] Entry $entry): Response
{
    return Inertia::render('Site/Articles/Show', [
        'entry' => [
            'title' => $entry->title,
            'url' => $entry->getUrl(),
            'summary' => $entry->getFieldValue('summary'),
        ],
        'isPreview' => $entry->previewing,
    ]);
}
```

That field belongs to this example project, not the package. Project PHP code
must choose public values explicitly; do not pass whole elements or queries as
props. The Playground's separate project page exposes `location`, and its
`config/site-content.php` permits another section/field configuration without
package edits. Canonical links use `Entry::getUrl()`.

Craft attaches `ResolveElementRoute` to registered site routes. A required
`#[CurrentElement]` parameter returns 404 when no compatible live or authorized
preview element matched. Disabled, future, expired, deleted and unpublished
content keeps Craft's availability rules.

## Route precedence

- Register a matching GET/HEAD route for every element destination. Named and
  registered controller destinations must match the request URI, domain and
  method. A destination name alone does not grant access.
- Register controller actions explicitly so their middleware executes. Native
  unregistered-controller fallback can dispatch for all HTTP methods without a
  separately registered route's middleware; this integration does not use it.
- Remove an existing `/` redirect if the homepage single should resolve there.
  Craft's native URI resolver recognizes the homepage without a demo prefix.
- Craft alpha.20 registers project-config dynamic **view** routes at runtime.
  They take precedence when they replace the same method/domain/URI signature,
  including cached application routes. Keep their signatures disjoint from
  Inertia pages. A winning dynamic view remains a native view response; the
  package does not reinterpret its template as a Vue component or override it.
- Other explicit project endpoints retain normal Laravel/Craft handling. There
  is no package `SetRoute` listener or catch-all to intercept them.

## Response policy and preview limits

For now, every opted-in public response carries `private, no-store` and
`X-Robots-Tag: noindex, nofollow` on initial HTML and Inertia JSON. This deliberately
retains the experiment's conservative policy. Public cache/indexing policy is
STEP-06; no production-cache claim is made here.

The project owns SSR policy. Playground keeps these example routes client-side
and leaves its existing authenticated app SSR and Craft CP policy intact. The
package does not replace the host SSR gateway or supply an SSR bundle.

Craft authorizes and resolves preview elements. Project pages consume the native
matched element and `previewing` state. Site IDs, translated URLs, domains and
subdirectory roots stay with Craft. See the [site and preview integration](docs/CONTENT.md#native-sites-and-preview).

## Tested Craft baselines

STEP-03's approved development baseline is the exact official source/assets pair:

| Package | Official commit |
| --- | --- |
| `craftcms/cms` | `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` |
| `craftcms/cms-assets` | `17973259d5727a1e50a1fc85dd90566b889407af` |

The [test metadata](tests/e2e/official-craft.json) comes from those fixed manifests,
with exact source and dist references. The consuming root requires both packages
as `6.x-dev as 6.0.0-alpha.20` and uses those package repositories ahead of Packagist.
The alias only satisfies the existing plugin constraint and CMS `self.version`
asset constraint. It does not make this a released alpha.20 environment. The
pinned CMS still reports an alpha.20 version constant, so the UI version string
alone is insufficient evidence. Root and runtime locks plus installed source and
published asset hashes identify the actual tested environment.

A plugin dependency hash alone is not a reproducible root pin; Composer also
[reads metadata from the branch when using commit references](https://getcomposer.org/doc/04-schema.md#require).
The harness checks both package refs/manifests and all native published CP asset
bytes before seeding. It does not build Craft's private icon assets locally.

The official source has the new ordinary-editor and standalone Vue Live Preview.
Released alpha.20 retains the recorded legacy WeakMap failure and is not accepted
for the complete editorial criterion. This is a tested development-commit claim,
not a blanket support claim for released Craft 6. See [STEP-03 evidence](reports/e2e/STEP03.md).

## Development and acceptance

See the [STEP-01 result and limits](reports/e2e/STEP01.md) for current scoped
acceptance and the separately failing editor-preview regression.

The package contains Playwright integration tests. `npm ci`,
`npm run typecheck` and `npm run format:check` check its test tooling. PHP syntax
and Composer validation apply to the runtime. Build and Vue unit checks run in
the consuming application, using its existing tooling.

`npm run test:e2e:prepare -- --fresh --working-tree` snapshots the sibling Playground's tracked
and untracked, non-ignored working files into `tests/e2e/.runtime`, installs it
with this package, creates an isolated database and seeds test-only content. It
does not commit the source or use the original app database. The copied older
project-config YAML is retained as evidence and omitted only in the fresh test
snapshot, so the pinned installer initializes its native schema; this does not
verify an upgrade of the original database/configuration. The script records
source hashes so local, uncommitted examples remain traceable. Follow its output
for PHP/SSR servers, then run `npm run test:e2e`. The `step03` project depends on the STEP-01/02
projects and seeds additional isolated sites only after those checks. Use a fresh
snapshot for the complete suite. Current output defaults to `reports/e2e/step03/`;
historical alpha.20 output remains separate. STEP-01 outputs live under the
ignored `reports/e2e/step01/`; the original 26 passed / 1 failed evidence is
preserved separately.

## Content and queries

Projects own explicit content projections, native Craft queries and Vue presentation.
See [the content convention](docs/CONTENT.md) for native Markdown, nested Matrix,
relations, globals, taxonomy, responsive assets and URL-based pagination across
two schemas, and [STEP-02 evidence](reports/e2e/STEP02.md) for scope and limits.

The legacy `diagnose-live-preview.mjs` script is retained only for the released
alpha.20 reproduction (original revision `2c670fe`); it rejects the current dev
runtime before writing historical output. The official baseline's native Twig
control is part of the strict `editor-preview` project.
