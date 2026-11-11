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
   available to Composer as `robuust/craft-inertia`, with PHP namespace
   `Robuust\CraftInertia`, native handle `inertia` and display name `Inertia`.
   Distribution stays private and the license stays proprietary.
   The [README path repository](../README.md#install) supports local development;
   `0.1.0` there is a local repository version, not a published release.
2. Require the package in the consuming project and install it through Craft:

   ```sh
   composer require robuust/craft-inertia:0.1.0 --no-interaction
   php artisan craft:plugin:install inertia --no-interaction
   php artisan vendor:publish --tag=craft-inertia-config --no-interaction
   ```

   Craft's plugin discovery owns provider registration. Do not also add the
   plugin to `bootstrap/providers.php` or Laravel package discovery. The plugin
   publishes configuration only; there is no plugin asset or UI build. Native
   CP assets remain Craft's `craft:setup:publish` responsibility.
3. Supply a project Blade root and page resolver. Use a view namespace, such as
   `frontend::app`, to distinguish the public root from Craft's own `app` view.
   Set `config/craft-inertia.php` to `['rootView' => 'frontend::app']`.
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
    && app(\CraftCms\Cms\Plugin\Plugins::class)->isPluginEnabled('inertia');
```

The project middleware calls the public adapter only when this predicate is
true. The controller uses the same predicate to choose `Inertia::render()` or
its project-owned native view. Check both sides: Composer classes still exist
while Craft disables the provider, so unconditionally rendering Inertia or
applying its middleware will not opt out automatically.

```sh
php artisan craft:plugin:disable inertia --no-interaction
php artisan craft:plugin:enable inertia --no-interaction
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
# Optional unique project for repeated proofs; use it for cleanup too.
export POC_INSTALL_COMPOSE_PROJECT=craft-inertia-installation
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
  -p "${POC_INSTALL_COMPOSE_PROJECT:-craft-inertia-installation}" down
```

Retain the reports and locks. Before repeating a fresh install, archive this
runtime, remove only this task's database volume with the same Compose project
and `down --volumes`, and use a new evidence directory. Never reset the
original application or another slice's runtime.

## Remaining boundaries

[STEP-07A evidence](../reports/e2e/STEP07A.md) records exactly what passed.
Upgrades and migration from an existing Twig site need project-specific route,
template, field, action and privacy acceptance. The bounded [identity migration](#migrate-the-legacy-package-identity) has its own
proof. General third-party compatibility, native account correction, production
deployment and public release remain outside it.
Full STEP-05, full AC-06 and the remaining AC-07 clauses stay open.


## Migrate the legacy package identity

STEP-07C changes Composer `robuust/craft-inertia-poc` to `robuust/craft-inertia`,
PHP `Robuust\CraftInertiaPoc` to `Robuust\CraftInertia`, native Craft handle
`inertia-poc` to `inertia`, and display name to `Inertia`. The npm harness is
`@robuust/craft-inertia` and remains private. Schema version `1.0.0`, dependency
constraints and proprietary license are unchanged. There is no public release,
Packagist registration, npm publication or compatibility alias.

Integration configuration is **`config/craft-inertia.php` / `craft-inertia.*`**,
with publish tag `craft-inertia-config`. Preserve the existing `rootView` and
`indexingPolicy` values. The official `inertiajs/inertia-laravel` adapter owns
`config/inertia.php` / `inertia.*`, including SSR; never rename our old config to
`inertia.php`, merge our defaults there or overwrite that file.

The fixed native `Plugins` API has no handle-rename operation. It uninstalls the
old registration and installs the new one. This core has no content tables or
install migrations, but that does not make an arbitrary project's uninstall
safe. Back up its database, project config and source; inventory its own hooks,
classes, cached routes/config and workers first. Keep the exact old package
available until its caches are cleared and native uninstall completes.

1. Select the project's explicit native fallback/opt-out, keep it active for the
   replacement and stop long-lived workers. Record whether the old plugin was
   enabled. Clear caches **while old classes still exist**:

   ```sh
   php artisan route:clear --no-interaction
   php artisan config:clear --no-interaction
   ```

   Native uninstall rejects a disabled plugin without force. If disabled,
   restore/enable the known old source with the project still opted out, then
   uninstall normally. Do not force a missing-source uninstall based on this proof.

   ```sh
   # Only if the known old installation was disabled:
   php artisan craft:plugin:enable inertia-poc --no-interaction
   php artisan craft:plugin:uninstall inertia-poc --no-interaction
   ```

2. Change the consuming Composer requirement and path-repository version key to
   `robuust/craft-inertia`, removing the old requirement. Use the same approved
   version and authenticated private source; `0.1.0` in these path examples is
   local Composer metadata, not a published tag. Update only the two package
   identities; preserve every other package record in the root lock:

   ```sh
   composer update robuust/craft-inertia robuust/craft-inertia-poc \
     --no-scripts --no-interaction
   ```

3. Change project imports to `Robuust\CraftInertia`, native registry predicates
   to handle `inertia`, and config reads to `craft-inertia.*`. Move the custom
   `config/inertia-poc.php` to `config/craft-inertia.php` without discarding values.
   Reconcile an existing destination rather than overwriting it. Do not edit
   plugin DB rows/project YAML or register a second provider.

   ```sh
   php artisan package:discover --no-interaction
   php artisan craft:plugin:install inertia --no-interaction
   php artisan vendor:publish --tag=craft-inertia-config --no-interaction
   ```

   Do not use `--force` on publication over custom config. Native install enables
   the new plugin. If the previous installation was intentionally disabled,
   disable `inertia` again before restoring project traffic.

4. Rebuild configuration and routes, restart workers, then restore the intended
   project opt-in. Use separate `config:clear` and `config:cache` commands on this
   Craft revision. Verify native fallback, direct HTML, Inertia JSON, statuses,
   custom root/indexing policy and unchanged official adapter SSR configuration.
   Verify project data before removing backup/recovery material.

The reproducible disposable proof uses the immutable merged predecessor
`935df5dc922d391cecd572cf59149c7864614b38` via `git archive`. It installs that
legacy package first, tests enabled/disabled fallback, clears old caches,
uninstalls natively, changes Composer/project source/config and installs the new
identity. Nine fresh-boot checkpoints compare native users/sites/elements,
content tables, unrelated project config and a nonempty project-owned table.
A custom root and indexing callable survive; the official SSR config file stays
byte-identical. Other Composer package records stay identical. The archived
fixture receives only current task storage/mail isolation and a unique Compose
project; archived package source/config/metadata stay unchanged.

```sh
export POC_IDENTITY_REPORT_DIR=/absolute/new/task-owned/identity-evidence
node tests/e2e/identity-migrate.mjs
```

The proof stops its database on success and retains the runtime, locks and
reports. On failure, inspect its task logs and stop the Compose project printed
in its patched legacy installer; retain evidence before repeating with a new
directory. It does not exercise missing vendor recovery, a forced uninstall,
an original database upgrade, custom uninstall hooks or all historical versions.
See [STEP-07C evidence and limits](../reports/e2e/STEP07C.md).
