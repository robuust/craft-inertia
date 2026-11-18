# Upgrade Craft Inertia to 1.0.2

| Installed version | Craft handle | Upgrade |
| --- | --- | --- |
| `0.1.0-beta.3` | `inertia` | [Remove the beta registration with beta.3 code, then install under the new handle](#from-beta3). |
| `1.0.0` or `1.0.1` | `craft-inertia` | [Keep the registration and update field selection and the package](#from-100-or-101). |

The Composer package is `robuust/craft-inertia` in each release. Rehearse the
upgrade with the [published 1.0.2 package](https://packagist.org/packages/robuust/craft-inertia#v1.0.2)
and retain the resulting consumer lockfile.

## Prepare public field selections

Beta.3, 1.0.0 and 1.0.1 include every custom layout field when no rule matches. In
1.0.2 an entry with no matching rule keeps its identity and returns empty
`fields`. List the fields your project deliberately exposes in
`config/craft-inertia.php`. For example, an `article` page that reads only
`entry.fields.summary` can use:

```php
'entryFields' => [
    'article' => ['summary'],
],
```

Include the handle of each relation or Matrix field your page reads on its
parent type. Then select the fields used on each related entry type or Matrix
child type. Check properties that Vue reads directly, such as
`entry.fields.related.length`, before the update. Each projected entry applies
its own rule. A type rule takes precedence over the `'*'` fallback, and `[]`
selects no custom fields.

If you intentionally expose every layout field for unmatched types, use the
following fallback with 1.0.2. Keep any existing type rules alongside it.

```php
'entryFields' => [
    '*' => '*',
    'article' => ['summary'],
],
```

This fallback exposes future fields on unmatched types as well, including
internal fields and fields hidden by editor layout conditions. You can apply
list-based rules with beta.3, 1.0.0 or 1.0.1. Deploy `'*' => '*'` only together with
1.0.2, because the earlier releases require a list as the rule value.

`entryFields` governs the entries the plugin projects. Selected composite fields
such as native Content Blocks can carry nested values from Craft's own
serializer. Review those values and use a project field serializer where the
frontend needs a narrower value. Also review custom `fieldSerializers`,
`publicProps`, `head`, and props returned by explicit project controllers.
Verify the intended values in initial HTML page data and Inertia JSON, including
nested entries and metadata. See the [content contract](CONTENT.md) and
[extension points](EXTENDING.md).

## From beta.3

### Remove the old registration

1. In each environment, check `composer show robuust/craft-inertia` and
   `php artisan craft:plugin:list`. Before acting on `inertia`, confirm that
   the row belongs to `robuust/craft-inertia` at beta.3. Record the persisted
   enabled state in project config and any separate `disabledPlugins` or safe
   mode override.
2. Schedule maintenance or switch traffic to the project's existing native
   fallback. Pause long-lived processes while you change the registration.
   Save the matching beta.3 release, Composer manifest and lock, database
   backup, Craft project config, and project-owned frontend configuration and
   assets. Keep the beta.3 code and corresponding project config available
   in the environment for the uninstall.
3. If Craft force-disables the handle through `disabledPlugins` or safe mode,
   temporarily lift that restriction in the isolated environment. If project
   config is read-only, use the project's maintenance procedure to permit
   native enable and disable commands. Clear cached config, then temporarily
   enable the registration if it was disabled. An already enabled registration
   can proceed directly to uninstall.

   ```sh
   php artisan config:clear --no-interaction
   php artisan craft:plugin:enable inertia --no-interaction
   ```

4. While Composer still has beta.3 installed, remove its registration.

   ```sh
   php artisan craft:plugin:uninstall inertia --no-interaction
   php artisan craft:plugin:list --no-interaction
   ```

   Verify before changing packages that the old handle is no longer installed
   and Craft removed `plugins.inertia` from project config. Use Craft's native
   commands to change registration and generate YAML. See the
   [Craft 6 plugin lifecycle](https://github.com/craftcms/cms/blob/6.0.0-beta.1/src/Plugin/Plugins.php).

### Install under the new handle

1. Update Composer in your update checkout and deploy the reviewed lockfile
   with normal package discovery.

   ```sh
   composer require 'robuust/craft-inertia:^1.0.2' --with-all-dependencies --no-interaction
   ```

2. Change host registry predicates to `craft-inertia`. Keep the
   `inertia:` section templates, existing content,
   Vue pages, Composer package, namespace, and `config/craft-inertia.php`.
   Merge the prepared `entryFields` selections into that file. The official
   adapter's settings stay under `inertia.*`.
3. Register the plugin. Publish configuration if it is missing and reconcile
   an existing file in place.

   ```sh
   php artisan craft:plugin:install craft-inertia --no-interaction
   php artisan vendor:publish --tag=craft-inertia-config --no-interaction
   ```

4. Restore the previous enabled state. If previously disabled, run
   `php artisan craft:plugin:disable craft-inertia --no-interaction`. Restore
   any environment overrides and use `craft-inertia` in `disabledPlugins`.
   Restore any administrative settings changed for the lifecycle commands.
5. Rebuild the affected config, route and view caches and any changed client
   and SSR assets. Restart long-lived web, queue and SSR workers. Verify that
   `craft:plugin:list` reports `craft-inertia` for the Robuust package with the
   intended state, and check the generated config for the new handle. On
   enabled installations, verify that existing mapped pages render with the
   intended public data. On disabled installations, verify the native
   fallback before reopening traffic.

### Propagate project config

Rehearse the cutover in the environment that owns the committed project config.
Review Craft's generated YAML diff for removal of `plugins.inertia`, addition
of `plugins.craft-inertia` with the intended state, and the updated
`dateModified`. Retain unrelated content and template configuration.

For each target database, first run the beta.3 removal steps with the old
release and its project config. Then deploy the new package and register it
under the new handle. Apply the reviewed project config through the project's
usual workflow before checking the final enabled state. Defer automated
project-config application until both native registration steps are done. Craft's
`craft:project-config:apply` installs added handles before removing old ones,
so a single apply after replacing the beta files cannot stand in for the
beta.3 uninstall. Coordinate the generated enabled state with existing
environment-specific config or disable overrides. Run the lifecycle commands
once for each shared database, and switch all web and worker nodes serving it
to the matching release. See the
[Craft 6 project-config command](https://github.com/craftcms/cms/blob/6.0.0-beta.1/src/ProjectConfig/Commands/ApplyCommand.php).

### Roll back to beta.3

Keep traffic and writes isolated while restoring the matching beta.3 code,
Composer manifest and lock, database snapshot, project config, host settings
and frontend assets as a set. If writes resumed after that snapshot, use the
project's recovery procedure to account for them. Rebuild the caches and
restart workers. Confirm that the old `inertia` registration is back in its
original enabled state and the mapped pages or native fallback work.

## From 1.0.0 or 1.0.1

1. Keep the existing `craft-inertia` registration. Back up the current lockfile,
   project frontend settings and assets. Prepare the public field allowlists,
   or prepare the deliberate `'*' => '*'` fallback to deploy with 1.0.2.
2. Resolve and review the new package and lock in your consuming project.

   ```sh
   composer require 'robuust/craft-inertia:^1.0.2' --with-all-dependencies --no-interaction
   ```

3. Deploy the package and prepared `config/craft-inertia.php` together.
   Rebuild config caches and any affected route or view caches. Rebuild
   client and SSR assets if the frontend changed, then restart long-lived
   processes using the previous code or config.
4. Verify HTML, Inertia JSON, and any configured SSR for mapped pages, including
   related entries and Matrix children. Confirm which fields appear on each
   projected entry and inspect the nested data in selected composite fields.
   If the update needs to be rolled back, restore the previous package version
   with its matching lockfile, settings and assets. Rebuild caches and restart
   workers again.

Use the [compatibility matrix](COMPATIBILITY.md) to record results against the
actual consumer lockfile and installed release.
