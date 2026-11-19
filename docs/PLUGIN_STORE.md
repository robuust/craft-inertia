# Craft Plugin Store listing

The public [Craft Inertia listing](https://plugins.craftcms.com/craft-inertia)
is enabled. Craft's [catalog record](https://api.craftcms.com/v1/plugin-store/plugin/craft-inertia)
records approval on 2026-11-19 at 03:54:30 UTC. The catalog and its
[changelog](https://plugins.craftcms.com/craft-inertia/changelog) show
1.0.2, matching the [GitHub tag](https://github.com/robuust/craft-inertia/releases/tag/v1.0.2)
and [Packagist release](https://packagist.org/packages/robuust/craft-inertia#v1.0.2).
Installation in a clean Craft 6 project remains to be verified.

## Published details

| Field | Value on 2026-11-19 |
| --- | --- |
| Name | Craft Inertia |
| Developer | Robuust Digital |
| Handle | `craft-inertia` |
| Composer package | `robuust/craft-inertia` |
| Repository | [robuust/craft-inertia](https://github.com/robuust/craft-inertia) |
| Compatibility | Craft 6. The package requires Craft CMS `^6.0.0-beta.1`, PHP `^8.5`, Laravel `^13.38`, and Inertia Laravel `^3.7`. |
| Documentation | [Installation and project setup](https://github.com/robuust/craft-inertia/blob/main/docs/INSTALLATION.md) |
| Support | [support@robuust.digital](mailto:support@robuust.digital) |
| Changelog | [Store changelog](https://plugins.craftcms.com/craft-inertia/changelog) and [repository changelog](https://github.com/robuust/craft-inertia/blob/HEAD/CHANGELOG.md) |
| Icon | [Store icon](https://pluginicons.craft-cdn.com/craft-inertia.svg?1795009139) |
| Edition | Standard, USD 49 initial and USD 19 renewal, under the [Craft License](../LICENSE.md) |
| Categories | Development, Performance, Templating |
| Keywords | craft, craftcms, inertia, laravel, vue |

## Published short description

Render Craft CMS 6 entries as Vue pages with Inertia.js while keeping Craft’s native URLs and previews.

## Draft replacement for the long description

The live description still says layout fields are included by default and
describes the plugin as beta. The copy below reflects the 1.0.2 release.

### Turn a Craft entry into a Vue page

Set a section’s Template to `inertia:Site/Home` to render it with your
`Site/Home.vue` component. Craft resolves the URL, site, and entry. Editors
can preview authorized drafts using the same Vue page.

### Choose the custom fields Vue receives

In `config/craft-inertia.php`, list the public field handles under each
entry type in `entryFields`. Each entry the plugin projects, including
Entries relations and Matrix children, uses its own rule. Without a matching
rule, the entry keeps its identity and returns `fields: []`. To expose all
layout fields for unmatched types, set `'*' => '*'` in `entryFields`. Selected
composite fields can include nested values from their
[native serializer](https://github.com/robuust/craft-inertia/blob/main/docs/CONTENT.md#field-values).
Read the
[upgrade guide](https://github.com/robuust/craft-inertia/blob/main/docs/UPGRADE.md)
before updating from an earlier version.

### Configure your frontend

Your project supplies the Vue pages, Blade root, and Vite build. You can
enable guest server-side rendering and connect project forms to supported
native Craft actions. The [quickstart](https://github.com/robuust/craft-inertia/blob/main/docs/QUICKSTART.md)
walks through the first page, optional scaffold, and setup diagnostics.
The [Contact Form integration](https://github.com/robuust/craft-inertia/blob/main/docs/CONTACT_FORM.md)
uses a separate optional plugin.

### Requirements

Craft Inertia 1.0.2 is a stable plugin release for the Craft 6 prerelease.
It requires Craft CMS `^6.0.0-beta.1`, PHP `^8.5`, Laravel `^13.38`, and
Inertia Laravel `^3.7`. See the
[installation guide](https://github.com/robuust/craft-inertia/blob/main/docs/INSTALLATION.md)
and [compatibility notes](https://github.com/robuust/craft-inertia/blob/main/docs/COMPATIBILITY.md).

## Installation paths

**Control panel.** Open **Plugin Store** in a compatible Craft 6 development
environment with admin changes and updates enabled. Find **Craft Inertia**
by Robuust Digital and choose **Try**, or **Install** if offered for your license.

**Composer.** For a fresh installation, use an existing Craft 6 project that
allows its prerelease dependencies. From the project root, run:

```sh
composer require 'robuust/craft-inertia:^1.0.2' --with-all-dependencies
php artisan craft:plugin:install craft-inertia
```

**After installing by either path**, run
`php artisan vendor:publish --tag=craft-inertia-config` and continue at
[quickstart step 2](QUICKSTART.md#2-set-up-the-public-blade-root).

## Remaining Store work

The public catalog contains one image, the
[request-flow diagram](https://pluginscreenshots.craft-cdn.com/craft-inertia/_2200x2200_fit_center-center_none/craft-inertia-readme-diagram.png?1795008570).
Capture these three views from the same working demo entry:

1. Craft section settings showing the native URI format and the
   `inertia:Site/Home` template.
2. The published Vue page with selected fields rendered at its native URL.
3. Craft’s native preview with a draft edit visible in the Vue page.

Publishing the corrected description and demo screenshots requires Store
edit access. In clean Craft 6 consumers, verify both installation paths and
follow the quickstart through the first native entry. Record the results in
[#27](https://github.com/robuust/craft-inertia/issues/27).
