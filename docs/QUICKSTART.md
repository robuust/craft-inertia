# Your first Craft Inertia page

Start with an existing Craft 6 site and render one native entry at
`/inertia-quickstart`. Craft resolves the entry and its URL. Your project supplies
the Blade root, Vite build and Vue page. This example starts with client rendering.

## 1. Install and publish the configuration

Use this step for a fresh installation from Packagist. For an existing
installation, including beta.3 under the old `inertia` handle, follow the
[upgrade guide](UPGRADE.md) before changing the Composer requirement. If you
already installed a [pinned candidate](INSTALLATION.md#try-a-pinned-candidate),
keep it pinned and continue at [step 2](#2-set-up-the-public-blade-root).
If you installed through the [Plugin Store](INSTALLATION.md#install-from-the-plugin-store)
and published the configuration, do the same.

Run these commands from the Craft project root. Check the [PHP, Craft and
adapter requirements](INSTALLATION.md#requirements-and-ownership) first.

```sh
composer require 'robuust/craft-inertia:^1.0' --with-all-dependencies
php artisan craft:plugin:install craft-inertia
php artisan vendor:publish --tag=craft-inertia-config
```

Craft discovers the plugin provider automatically. Merge the settings below
into any existing `config/craft-inertia.php`.

### Preview or copy the example files

With Craft Inertia 1.0.2 installed and enabled, preview the scaffold from the
consuming project root:

```sh
php artisan craft-inertia:scaffold --no-interaction
```

Use `--write` to copy the missing Blade root, Vue entry point and example page.
Merge changes into preserved project files manually. To replace different
files, preview the plan with `--force` before applying it with
`--write --force`. The command leaves identical files, dependencies, locks,
Vite, provider configuration, routes, content and Craft project config in place.

Continue with step 2 to configure the namespace and field selection, then
follow the Vite and Craft configuration steps. If you copied a file in this
step, use that copy in place of the matching manual example below.

## 2. Set up the public Blade root

Add the `View` import and the namespace registration to your existing
`app/Providers/AppServiceProvider.php`.

```php
use Illuminate\Support\Facades\View;

// Inside AppServiceProvider::boot():
View::addNamespace('frontend', resource_path('views/frontend'));
```

Set these keys in the published `config/craft-inertia.php`. The key
`quickstartPage` is the entry type handle. The explicit `'*' => []` fallback
keeps custom fields on unmatched types, including related entries and Matrix
children, out of page data across the 1.0 versions.

```php
'rootView' => 'frontend::app',
'entryFields' => [
    'quickstartPage' => ['summary'],
    '*' => [],
],
```

Create `resources/views/frontend/app.blade.php`.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $page['props']['site']['language']) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-craft-inertia::head :page="$page" />
    @vite('resources/js/app.js')
</head>
<body>
    <x-inertia::app />
</body>
</html>
```

This namespaced view is the public root for native mapped pages. Use the
project's existing root and Vue entry if they already serve that purpose. Keep
the Blade asset path and Vite input aligned. To update metadata on subsequent
Inertia visits, add the packaged [Vue Head component](HEAD.md#packaged-head-components)
to the project layout after setting up the first page.

## 3. Add the Vue entry and Vite build

Use Node 24 and the project's package manager. This example uses npm. If you are
starting without a `package.json`, run `npm init -y` first. In a project with a
frontend build, add the missing dependencies and merge the settings into its
existing Vite configuration.

```sh
npm install 'vue@^3.5' '@inertiajs/vue3@^3.11'
npm install -D 'vite@^8' 'laravel-vite-plugin@^3.2' '@vitejs/plugin-vue@^6' '@inertiajs/vite@^3.11'
```

For a new Vite setup, create `vite.config.mjs`.

```js
import inertia from '@inertiajs/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.js'] }),
        vue(),
        inertia({ ssr: false }),
    ],
});
```

Create `resources/js/app.js`. The official Inertia Vite plugin generates the
resolver from `pages`. Paths are relative to this entry point and match the
component name exactly, including case.

```js
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({ pages: './pages' });
```

Create `resources/js/pages/Site/Quickstart.vue`.

```vue
<script setup>
defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <main>
        <h1>{{ entry.title }}</h1>
        <p v-if="entry.fields.summary">{{ entry.fields.summary }}</p>
    </main>
</template>
```

For a project starting without SSR, set `INERTIA_SSR_ENABLED=false` in `.env`
so the Laravel adapter also uses client rendering. In a project already using
SSR, keep its existing Vite and adapter settings and use its public rendering
policy to select which pages may use SSR. See [Inertia client setup](https://inertiajs.com/docs/v3/installation/client-side-setup)
and [Laravel Vite configuration](https://laravel.com/docs/13.x/vite).

Build the client bundle. With the default Laravel Vite paths, the output includes
`public/build/manifest.json`, keyed by `resources/js/app.js`.

```sh
npm exec -- vite build
```

## 4. Map a Craft entry to the Vue page

1. In Craft's control panel, create a Plain Text field with handle `summary`.
2. Create an entry type with handle `quickstartPage` and add `summary` to its
   field layout.
3. Create a **Single** section using that type. In the settings for the site
   serving the entry, set the URI to `inertia-quickstart` and the Template to
   `inertia:Site/Quickstart`. Leave any explicit route destination empty.
4. Set the entry title and summary, and save the entry as live for that site.

Open the entry's published URL. On a site rooted at the domain, its path is
`/inertia-quickstart`. The Vue page displays the entry title and summary. The
native mapping supplies the page without adding a Laravel route or an entry
controller. For an existing section, keep its URI and change only the selected
site's Template after checking the component and the fields it exposes.

## Read-only troubleshooting checklist

With Craft Inertia 1.0.2 installed and enabled, run the read-only setup command
from the Craft project. For earlier versions, use the manual checklist below.

```sh
php artisan craft-inertia:doctor --component=Site/Quickstart --no-interaction
```

If the command is missing, use `php artisan craft:plugin:list` to confirm that
Craft discovered and enabled `craft-inertia`. Use `--pages` for a different
Vue pages directory, `--entry` for a different Vite input, and
`--build-directory` when overriding the Laravel Vite build directory in
Blade. The doctor checks file and manifest availability. Verify your actual
JavaScript resolver, dev server reachability and form behavior using the
checks below.

| Symptom | Check |
| --- | --- |
| The native page never reaches Inertia | Check `php artisan craft:plugin:list` for enabled `craft-inertia`. Inspect the entry's live status, site, URL, section Template and any explicit route destination. The exact `inertia:` prefix opts in when native routing has no explicit destination. |
| The root view is missing or renders the control panel shell | Inspect the effective `craft-inertia.rootView`, the host provider's namespace registration, and the Blade file under that namespace. Check for stale cached configuration or an old worker after changes. |
| The page has HTML but Vue does not mount | Inspect the original HTML for the script emitted by `<x-inertia::app />` with the initial page data. Check that the Vite asset URLs load, the manifest contains the entry named in `@vite`, and the browser console has no module errors. If a Vite hot file is present, check that the browser can reach its dev server. |
| The component is missing | Match `Site/Quickstart` in the initial page data to `resources/js/pages/Site/Quickstart.vue` and inspect the case of each path segment. Check that the Inertia Vite plugin runs on the entry containing `createInertiaApp()`. |
| A field is missing or an unexpected field appears | Inspect `props.entry.fields` in the initial page data or the response to an Inertia visit. Check the selected entry type handle, the fields attached to its layout, and the effective `entryFields` map. |
| Control panel or native action traffic reaches the public app | Check Craft's effective CP URL, action trigger and any global Inertia middleware. Keep native handlers in charge. Before enabling SSR, apply the [CP and action exclusions](INSTALLATION.md#configure-the-project-frontend), including the dedicated CP origin when `cpTrigger` is `null`. |
| An unmapped or disabled page needs its native template | Verify that the original Twig/Blade template still exists and that the section points to it when switching back. An `inertia:`-only Template offers no automatic reconstruction of the original page. See [native fallback](INSTALLATION.md#disable-fallback-and-cached-routes). |

## Add SSR after the client page works

Apply the [CP and action exclusions](INSTALLATION.md#configure-the-project-frontend)
before enabling SSR. For this example, change `inertia({ ssr: false })` to
`inertia()` and enable the adapter's `inertia.ssr.enabled` setting. With the
official Vite plugin, the same Vue entry can serve development SSR. For
production, build the client and SSR
bundles with `vite build` and `vite build --ssr`, then run a private, supervised
`php artisan inertia:start-ssr` process. Use the project's existing SSR build
and process configuration when present.

For diagnosis, check the SSR bundle at the adapter's configured path and use
`php artisan inertia:check-ssr` from the PHP environment. In containerized
development, verify the server-side `inertia.ssr.hot_url` separately from the
browser's Vite URL. On an eligible guest request, inspect the original HTML for
the rendered entry title and verify that the client can still mount when SSR
is unavailable. See [installation and SSR](INSTALLATION.md#development-builds-and-ssr)
and the [Inertia SSR guide](https://inertiajs.com/docs/v3/advanced/server-side-rendering).

## When adding a native form

Generate the action URL, session CSRF token and encrypted redirect on each
request using `NativeForm::props()` inside the form page's private rendering
boundary. For a mapped entry, return the form prop from a scoped `publicProps`
callback and exclude the form page from guest SSR with `publicPagePolicy`. Keep
the props outside public content caches and metadata. Submit to the native
action using the returned token as `_token` and keep its native session and CSRF
middleware. See [native form props](FORMS.md#native-action-form-props) and the
[Contact Form example](CONTACT_FORM.md#form-props-and-errors).

To diagnose a first-visit failure, use a fresh browser context and inspect the
final responses for a direct HTML visit, the first Inertia navigation to the form,
and client rendering when SSR is unavailable. Confirm that the form token is in
the serialized page props and that the browser receives the session and
`XSRF-TOKEN` cookies with the expected domain and path. On partial visits that
first load the form, include its prop. Craft's lazy session can discard both
cookies when the session is unused and its token never appears in the final
response. Resolve and serialize the token for the form page itself so ordinary
content pages can keep their lazy session behavior. See the [installation finding](https://github.com/robuust/craft-inertia/issues/13#issuecomment-6824654375)
and [Craft's lazy session middleware](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Http/Middleware/StartLazySession.php#L108).
