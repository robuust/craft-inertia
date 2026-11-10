# Clean installation and native fallback

STEP-07A proves the independent clean-installation and opt-out clauses of AC-07.
It does not complete STEP-07 or establish general release readiness. The
[neutral consuming application](../tests/e2e/installation) has two ordinary
project pages, one Vue component and explicit public string/link props. It has
no sections, custom fields, entries, demo content or package-owned frontend.
The existing Playground remains a separate, local example.

## Validated baseline

| Dependency | Tested version |
| --- | --- |
| PHP | 8.5.12 |
| Craft CMS | `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` |
| Craft CP assets | `17973259d5727a1e50a1fc85dd90566b889407af` |
| Laravel | 13.40.0 |
| Inertia Laravel / Vue adapter | 3.7.0 / 3.11.0 |
| Vue | 3.5.45 |
| Vite / Laravel Vite plugin / Vue Vite plugin | 8.3.6 / 3.2.1 / 6.0.10 |
| Node / Yarn | 24.15.0 / 4.14.1 |

The root lock aliases Craft `6.x-dev` to `6.0.0-alpha.20` solely to satisfy the
existing Composer constraint. This is **not** released alpha.20 compatibility.
[Fixed metadata](../tests/e2e/official-craft.json) and the consuming application's
lockfiles own reproducibility. The broad package constraints are not a tested
support matrix. No floating latest-version install or dependency upgrade is
part of this slice.

## Installation checklist

1. Start from an independently installed Craft application on the pinned
   baseline, with its own environment, database and lockfiles. Keep existing
   application data and project config intact. Make this private package
   available to Composer, retaining `robuust/craft-inertia-poc`, the
   `Robuust\CraftInertiaPoc` namespace, `inertia-poc` handle and existing license.
   The [README path repository](../README.md#install) supports local development;
   `0.1.0` there is a local repository version, not a published release.
2. Require the package in the consuming project and install it through Craft:

   ```sh
   composer require robuust/craft-inertia-poc:0.1.0 --no-interaction
   php artisan craft:plugin:install inertia-poc --no-interaction
   php artisan vendor:publish --tag=inertia-poc-config --no-interaction
   ```

   Craft's plugin discovery owns provider registration. Do not also add the
   plugin to `bootstrap/providers.php` or Laravel package discovery. The plugin
   publishes configuration only; there is no plugin asset or UI build. Native
   CP assets remain Craft's `craft:setup:publish` responsibility.
3. Supply a project Blade root and page resolver. Use a view namespace, such as
   `frontend::app`, to distinguish the public root from Craft's own `app` view.
   Set `config/inertia-poc.php` to `['rootView' => 'frontend::app']`.
   Register the namespace in the project's provider. The fixture supplies
   [the root](../tests/e2e/installation/resources/views/app.blade.php),
   [client resolver](../tests/e2e/installation/resources/js/app.js),
   [Vue page](../tests/e2e/installation/resources/js/pages/Page.vue) and
   [controller](../tests/e2e/installation/app/Http/Controllers/PageController.php).
   Projects choose their own layouts, CSS, queries and explicit public props.
4. Opt in only selected project routes, after `web` and Craft middleware.
   The fixture uses [`PublicFrontend`](../tests/e2e/installation/app/Http/Middleware/PublicFrontend.php)
   on `/` and `/about`, with an ordinary API, feed and native page outside it.
   Do not put the public adapter on the entire web stack or Craft's CP/actions.
   Keep native element destinations and publication/preview authority in Craft
   when adding content; no section or field is needed for these static pages.

## Project opt-out and native disable/enable

Installing the package registers no public route or catch-all. It does not
convert existing Twig views, and disabling it cannot reconstruct old templates
or infer a project's fallback. The project must explicitly retain those native
routes/views and select them when the integration is off.

The fixture's project setting defaults **off**:

```php
// config/frontend.php
return ['enabled' => (bool) env('FRONTEND_ENABLED', false)];
```

Its single project predicate combines that setting with Craft's registry:

```php
return config('frontend.enabled')
    && app(\CraftCms\Cms\Plugin\Plugins::class)->isPluginEnabled('inertia-poc');
```

The project middleware calls the public adapter only when this predicate is
true. The controller uses the same predicate to choose `Inertia::render()` or
its project-owned native view. Check both sides: Composer classes still exist
while Craft disables the provider, so unconditionally rendering Inertia or
applying its middleware will not opt out automatically.

```sh
php artisan craft:plugin:disable inertia-poc --no-interaction
php artisan craft:plugin:enable inertia-poc --no-interaction
```

Craft owns persisted plugin state and project config. The provider is absent
on a fresh boot while disabled and returns after enablement. Do not edit its
database row or YAML by hand. The per-request predicate continues to work with
`route:cache`; it is deliberately not evaluated only while registering routes.
Restart long-lived application workers after lifecycle/configuration changes.
Rebuild Laravel's configuration cache after changing `FRONTEND_ENABLED`.
The fixture also verifies an enabled plugin with project opt-out selected.

On this fixed Craft development revision, use separate `config:clear` and
`config:cache` commands when rebuilding an existing configuration cache.
Rebuilding directly over a cached configuration can raise a native duplicate
`APP_NAME` constant error. The fixture exercises the two-command sequence;
no native fix or upgrade acceptance is included.

Configured fallback pages retain status 200 and native content, including for
requests carrying `X-Inertia`; they omit the Inertia page envelope and project
bundle. An already-open Inertia client needs a full-page visit after switching
to native HTML, as the official client cannot treat that HTML as a page JSON
response. Unknown URLs remain native 404s. CP login, native session-info actions,
the project API and feed retain their own responses while enabled or disabled.
These are representative endpoint checks, not account or complete CP acceptance.

## Development, builds and SSR

Use the project's locked frontend dependencies and its Vite configuration.
The fixture reuses the approved dependency manifests and lockfiles only; it does
not import Playground routes, application code, config, schemas or content.
Its explicit `nodeLinker: node-modules` also lets a normal Node process resolve
the production SSR bundle's external dependencies.

```sh
corepack yarn install --immutable
corepack yarn vite                  # local project development
corepack yarn vite build            # public client manifest and assets
corepack yarn vite build --ssr      # project SSR entry and output
php artisan inertia:start-ssr
```

The fixture's [Vite config](../tests/e2e/installation/vite.config.js) declares its
own client and SSR entries. Its SSR process listens on loopback port 13727,
matching `INERTIA_SSR_URL=http://127.0.0.1:13727`. Keep the project SSR service
private, supervise it separately, deploy matching client/SSR bundles and retain
the runtime dependencies. An absolute `--runtime` can select the supported Node
binary when the host also has another version installed. Development and
deployment service configuration belong to the consuming project.

The project's provider uses official `Inertia::disableSsr()` to render only
its selected static pages; the CP must never reach this resolver. It also
respects `inertia.ssr.enabled`. The fixture has only fixed public props. For
real content, accounts, preview, form/flash state and errors, apply the
[existing SSR privacy and response policies](RENDERING.md), rather than treating
this small fixture as a production application. HTTP output remains private,
no-store; public output caching is separate.

## Repeat the isolated proof

The install harness is independent of the demo-specific `prepare.mjs` and all
existing Playwright projects. It requires the approved local Playground
dependency metadata, Composer, PHP 8.5, Node 24, Yarn, Docker and Chrome. Set an
absolute task-owned evidence directory; the runtime refuses to overwrite an
existing installation.

Preparation generates `bootstrap/database-target.json` in the disposable
runtime, independently of the caller's environment. The fixture checks its
loaded configuration immediately after Laravel's `LoadConfiguration`, before
any application provider registers. CLI, HTTP and PHPUnit boots therefore
reject foreign database tuples or application URLs, including cached config.
This fixture supports one checked PostgreSQL connection only: connection URLs,
read/write/direct endpoints and pooling overrides are rejected. Other connection
templates and connector options are removed before providers load. Laravel
normally lets URL fields/query parameters override individual `DB_*` values;
the fixture deliberately does not accept that alternate transport. A correct
cached target stays authoritative over inherited environment variables.

The guard does not sanitize or print caller credentials. If a launch fails with
`Installation fixture isolation`, remove conflicting inherited configuration and
repeat it. `APP_ENV=install` and project `FRONTEND_ENABLED` changes remain allowed.
Do not copy this task-only guard/target file into a consuming production project.
The regression probes stop before provider registration even when demonstrating
the previous failure, so sentinel values cannot cause a foreign DB connection.

Run from the plugin checkout:

```sh
export POC_INSTALL_REPORT_DIR=/absolute/task-owned/installation-evidence
node tests/e2e/install-prepare.mjs
cd tests/e2e/.runtime/installation
php vendor/bin/phpunit --log-junit "$POC_INSTALL_REPORT_DIR/phpunit.xml"
cd public
php -d opcache.enable=0 -S 127.0.0.1:18777 \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
# In another shell in this application:
php artisan inertia:start-ssr --runtime=/absolute/path/to/node24
# From the plugin checkout, in another shell:
npx playwright test --config=tests/e2e/install.config.ts
```

The browser suite changes only this fixture's native plugin state and cached
project setting, including a `route:cache` cycle, and restores them afterward.
The local PHP command disables bytecode caching so rapid configuration-file
replacement is visible on the next request; production workers need their
normal deployment/configuration restart process.
Stop the task-owned PHP/SSR processes when finished, then stop its database:

```sh
docker compose -f "$POC_INSTALL_REPORT_DIR/compose.yaml" \
  -p craft-inertia-installation down
```

Retain the reports and locks. Before repeating a fresh install, archive this
runtime, remove only this task's database volume with the same Compose project
and `down --volumes`, and use a new evidence directory. Never reset the
original application or another slice's runtime.

## Remaining boundaries

[STEP-07A evidence](../reports/e2e/STEP07A.md) records exactly what passed.
Upgrades and migration from an existing Twig site need project-specific route,
template, field, action and privacy acceptance. No package rename, namespace or
handle migration, license/distribution decision, third-party compatibility,
native account correction, production deployment or public release is included.
Full STEP-05, full AC-06 and the remaining AC-07 clauses stay open.
