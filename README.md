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

Craft authorizes and resolves preview elements. No query marker is treated as
permission, and no extra numeric preview target is added. Basic native URL/token
privacy belongs to STEP-01 checks; full revisions, multisite and editor preview
acceptance remain STEP-03. The historical editor Live Preview test failed with a
native Craft `WeakMap` error also reproduced in a Twig control with zero plugin
bundle requests. That failure remains unresolved; see the
[original report](reports/e2e/REPORT.md).

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
does not commit the source or use the original app database. The script records
source hashes so local, uncommitted examples remain traceable. Follow its output
for PHP/SSR servers, then run `npm run test:e2e`. STEP-01 outputs live under the
ignored `reports/e2e/step01/`; the original 26 passed / 1 failed evidence is
preserved separately.
