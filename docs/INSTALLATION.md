# Installation and native fallback

## Requirements and ownership

The package requires PHP `^8.5`, Craft `^6.0.0-beta.1` and the official Inertia
Laravel adapter `^3.7`, with Laravel `^13.38`. The inspected beta.1 baseline
uses CMS source
`05ce9b81be3704fb27da3a5a6670accfa49ec9a9`, CP assets
`87acafef481f17c12a10170211bbccda42588dbd` and native schema `6.0.0.36`.
See the [compatibility matrix](COMPATIBILITY.md#baseline) for the versioned
acceptance target and verification status.

The plugin contains PHP integration code and configuration. The consuming Craft
project owns its content schema, Vue pages, Blade root, frontend dependencies,
builds, runtime services and integration verification.

## Install from the Plugin Store

Open **Plugin Store** in the Craft 6 control panel and search for
[Craft Inertia by Robuust Digital](https://plugins.craftcms.com/craft-inertia).
On a compatible development installation with admin changes and updates
enabled, choose **Try** or **Install**, depending on the license shown by Craft.

After Craft installs and enables the plugin, publish its configuration:

```sh
php artisan vendor:publish --tag=craft-inertia-config --no-interaction
```

Continue at [quickstart step 2](QUICKSTART.md#2-set-up-the-public-blade-root).
The Plugin Store handles package installation and registration through Craft.

## Install from Packagist

Craft Inertia [v1.0.2](https://github.com/robuust/craft-inertia/releases/tag/v1.0.2)
is available on [Packagist](https://packagist.org/packages/robuust/craft-inertia#v1.0.2)
as `robuust/craft-inertia`. See [consumer checks](COMPATIBILITY.md#consumer-checks)
for installation and runtime verification status.
The existing Craft 6 project's root requirements must permit its beta
CMS/assets dependencies. No private VCS repository or GitHub token is needed.
The package is distributed under the [Craft License](../LICENSE.md).

For a new installation, run these commands from the consuming project's root.

```sh
composer require 'robuust/craft-inertia:^1.0.2' --with-all-dependencies --no-interaction
php artisan craft:plugin:install craft-inertia --no-interaction
php artisan vendor:publish --tag=craft-inertia-config --no-interaction
```

Versions 1.0.0 and 1.0.1 use the same handle and include every attached field
when no field rule matches. Their setup is documented in the
[tagged 1.0.1 guide](https://github.com/robuust/craft-inertia/blob/v1.0.1/docs/INSTALLATION.md).
Follow the [upgrade guide](UPGRADE.md) for an existing installation.

When replacing an existing private VCS requirement, remove its repository entry
and update the dependency and lock to the published release.

Craft discovers the plugin provider. Do not register it again in
`bootstrap/providers.php` or Laravel package discovery. Configuration publication
does not overwrite existing values without `--force`; reconcile existing files
instead of forcing publication. Craft's own CP assets remain the responsibility
of `craft:setup:publish`.

### Try a pinned candidate

To verify an unreleased change, use a clean clone of the plugin
checked out at the full candidate commit SHA. Add it as a path repository
in the consuming project's `composer.json`. Adjust the path to match where
Composer will run.

```json
{
    "type": "path",
    "url": "../craft-inertia-candidate",
    "options": {
        "symlink": false,
        "reference": "auto",
        "versions": {
            "robuust/craft-inertia": "dev-local-candidate"
        }
    }
}
```

```sh
composer require 'robuust/craft-inertia:dev-local-candidate' --with-all-dependencies --no-interaction
```

The version override is local to this Composer repository. Check the plugin's
`dist.reference` in the consumer lockfile against the candidate SHA and record
the locked dependencies with your [acceptance results](COMPATIBILITY.md). For a
new installation, run the native install and config publication commands above.
For an upgrade, follow the cutover order in the [upgrade guide](UPGRADE.md).

## Configure the project frontend

The [quickstart](QUICKSTART.md) covers the optional scaffold and read-only
diagnostics. Craft registers these commands when the plugin is installed and
enabled.

1. Supply a public Blade root with Vite assets, `<x-craft-inertia::head :page="$page" />` and
   `<x-inertia::app />`. Register a public view namespace and set
   `craft-inertia.rootView` to e.g. `frontend::app`, separate from Craft's CP root.
2. Supply the Vue entry point, page components and resolver. With the official
   `@inertiajs/vite` plugin configured alongside Laravel and Vue, the application
   entry point can call `createInertiaApp()` from `@inertiajs/vue3`.
3. Exclude native CP/actions from the public SSR bundle through the official
   gateway. Use the [provider example](../README.md#configure-the-consuming-project).
   Its CP exclusions use Craft's absolute CP URL. When `cpTrigger` is `null`,
   configure `baseCpUrl` with the dedicated CP origin first; concatenating a null
   trigger with `/*` would exclude the entire public website. Preserve existing
   host SSR callbacks and exclusions.
4. Keep adapter settings under `inertia.*` and integration settings under
   `craft-inertia.*`. Indexing defaults to denied; an explicit callable must
   establish public eligibility and return `true`. Craft's `disallowRobots` wins.

Layouts, CSS, website queries, metadata content and business rules remain
project-owned. There is no package-owned frontend build or npm package.

## Select native entry pages

Keep the section's native URI (`articles/{slug}`, `__home__` for a homepage,
or a native structure URI). Leave its explicit route destination empty and set
Template to `inertia:Site/Articles/Show`. Supply the matching Vue component through
the project's resolver, for example
`resources/js/pages/Site/Articles/Show.vue`.

In 1.0.2, each entry has identity and empty `fields` until a rule selects its
custom fields. For example, add this rule to the returned array in
`config/craft-inertia.php` to expose `entry.fields.summary` for the `article`
entry type.

```php
'entryFields' => [
    'article' => ['summary'],
],
```

Configure each related or Matrix entry type's own selection.
A type rule takes precedence over the `'*'` fallback. An empty list selects no
custom fields. See the [content contract](CONTENT.md) and [quickstart](QUICKSTART.md).

The exact `inertia:` prefix opts in. Separate component segments with `/`.
Segments can use letters, digits, `_` and `-`, and support bracketed names such
as `[slug]`. An empty or invalid component is a configuration error. Explicit
native route/controller destinations take precedence. Twig/Blade, dynamic view
routes, CP/actions, APIs and feeds keep their own handlers. Unknown URLs retain
native 404 handling.

## Bespoke project pages

Overview, search, private pages and business queries use explicit project routes
and controllers. Apply `Robuust\CraftInertia\Http\HandleInertiaRequests` only to
selected page routes after `web` and `craft`. Do not attach it to the whole web
stack or native actions. For an entry destination, consume Craft's
`#[CurrentElement] Entry` instead of looking up a slug or canonical ID again.
Registered destinations must match the native URI, domain and method.

If a manual integration supports opting out, use one project predicate both for
its middleware and for choosing the Inertia or native response:

```php
return config('frontend.enabled')
    && app(\CraftCms\Cms\Plugin\Plugins::class)->isPluginEnabled('craft-inertia');
```

Composer classes still exist while the plugin is disabled. Checking only at
route registration does not provide a per-request opt-out with cached routes.
Retain deliberate project fallback views and routes.

## Development, builds and SSR

Use the consuming project's locked dependencies and configured build scripts.
The official Inertia Vite plugin supports development SSR from the application
entry point. Cross-container development must make its SSR endpoint reachable
through `inertia.ssr.hot_url`.

Build matching client and SSR bundles for production and run the project's
`php artisan inertia:start-ssr` process with its supported Node version. Keep
the service private, supervise it separately and retain its runtime dependencies.
Projects with a separate SSR entry must configure it explicitly in Vite.
Deployment configuration, persistent asset storage and worker restarts belong
to the project.

Guest mapped pages respect the official SSR configuration and host callbacks.
Authenticated, token, preview and form-state requests stay outside guest SSR.
See [RENDERING.md](RENDERING.md) for the response and privacy contracts.

## Disable, fallback and cached routes

```sh
php artisan craft:plugin:disable craft-inertia --no-interaction
php artisan craft:plugin:enable craft-inertia --no-interaction
```

Native disable removes the listener on a fresh boot; enable restores it, including
with cached application routes. Restart long-lived workers after lifecycle
changes. Craft owns persisted plugin state and project config; do not edit its
database rows or YAML by hand.

An `inertia:` Template has no corresponding native Twig file. Disabling the plugin
therefore follows normal template handling and may return a missing-template
error. Configure and retain a real Twig/Blade template or explicit native
destination before opting out; the plugin cannot reconstruct the old design.
An already-open Inertia client needs a full-page visit when switching to native
HTML. Rebuild configuration caches with separate commands on this Craft baseline:

```sh
php artisan config:clear --no-interaction
php artisan config:cache --no-interaction
```

## Upgrade from the `inertia` handle

Use the [beta.3 upgrade procedure](UPGRADE.md#from-beta3) to move the
`inertia` registration to `craft-inertia`. For projects already using the new
handle, see [Upgrade from 1.0.0 or 1.0.1](UPGRADE.md#from-100-or-101).

## Migrate the legacy package identity

The old identity is Composer `robuust/craft-inertia-poc`, PHP
`Robuust\CraftInertiaPoc`, native handle `inertia-poc` and config `inertia-poc.*`.
The current identity is `robuust/craft-inertia`, `Robuust\CraftInertia`, native
handle `craft-inertia` and config `craft-inertia.*`. See the
[Craft License](../LICENSE.md).

1. Back up project source, database and configuration. Inventory custom hooks,
   cached routes/config and workers. Select the project's native fallback and
   stop long-lived workers. Clear old caches while the old classes still exist.
2. Uninstall the old plugin natively. A disabled plugin must first be restored
   and enabled with its known old source while the project remains opted out;
   do not force an uninstall with missing code.
3. Update the consuming Composer requirement to the new public package
   identity and remove the legacy private repository entry. The current plugin
   requires Craft beta.1 APIs, so upgrade the native
   CMS/assets and required dependencies deliberately if the old project used an
   earlier baseline. Review the resulting lock changes; this is not guaranteed
   to be an identity-only update with every other package record unchanged.
4. Update imports, registry predicates and config reads. Move custom
   `config/inertia-poc.php` values to `config/craft-inertia.php`, reconciling any
   existing destination. Keep the official `config/inertia.php` untouched.
5. Run package discovery, install `craft-inertia` natively and publish configuration
   without `--force`. Restore the old enabled/disabled intent, rebuild caches
   and bundles, restart workers and verify the project before restoring traffic.

For ordinary manual entry pages, retain native URIs/sites, remove the duplicate
detail route destination and choose `inertia:Component/Name`. Adapt Vue to
`entry.fields.<nativeHandle>`, native Matrix `type` and asset list cardinality.
Remove obsolete `pages[component]['fields']` settings. Special queries and
business rules stay with the project.

Verify fallback, HTML, Inertia JSON, statuses, intended field data, preview
identity, metadata and SSR in the consuming project. Historical migration
reports apply only to their original locked inputs and remain in Git history;
they are not a runnable migration procedure for current dependencies.

## Native Head setup

Use the packaged Vue Head component once in the native page layout, together
with the Blade component above. [HEAD.md](HEAD.md) documents the Vite alias,
shared metadata contract and generic project provider. Remove duplicate
title/canonical/robots renderers when migrating an existing frontend.

The provider supplies the complete `head.title`, including any project branding.
Remove transforming `title` callbacks from both client and SSR `createInertiaApp()`
setup (omit the option or keep it as identity), so Inertia Head and the non-SSR
Blade fallback render the same title.
