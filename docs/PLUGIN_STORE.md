# Craft Plugin Store submission reference

Robuust has submitted the Craft Inertia application. Approval, initial and
renewal prices, and screenshot completion are unverified. The current
[Packagist release](https://packagist.org/packages/robuust/craft-inertia) is
1.0.1. Version 1.0.2 is awaiting release.

## Listing details

| Field | Reference value |
| --- | --- |
| Name | Craft Inertia |
| Developer | Robuust |
| Handle | `craft-inertia` |
| Composer package | `robuust/craft-inertia` |
| Compatibility | Craft CMS `^6.0.0-beta.1` (Craft 6 prerelease), PHP `^8.5`, Laravel `^13.38`, and Inertia Laravel `^3.7`. See the [compatibility notes](https://github.com/robuust/craft-inertia/blob/main/docs/COMPATIBILITY.md) for planned acceptance versions and known limits. |
| Documentation | [Installation and project setup](https://github.com/robuust/craft-inertia/blob/main/docs/INSTALLATION.md) |
| Support | [GitHub issues](https://github.com/robuust/craft-inertia/issues) |
| Changelog | [`CHANGELOG.md`](../CHANGELOG.md) |
| Icon | [`resources/icon.svg`](../resources/icon.svg) |
| Distribution | Paid under the [Craft License](../LICENSE.md) |
| Suggested categories | Development, Templating |
| Keywords | craft, craftcms, inertia, laravel, vue |

## Short description

Render Craft CMS 6 entries as Vue pages with Inertia.js while keeping Craft’s native URLs and previews.

## Long description

Set a section’s Template to `inertia:Site/Home` to render it with your
`Site/Home.vue` component. Craft resolves the URL, site, and entry. Editors
can preview authorized drafts using the same Vue page.

Read selected custom fields by their Craft handles. Configure `entryFields`
by entry type for the main entry, related entries, and Matrix blocks. In
1.0.1, unmatched types expose their full field layout. Starting in 1.0.2,
they expose entry details and an empty field projection unless you configure
a rule. Review the [upgrade guide](https://github.com/robuust/craft-inertia/blob/main/docs/UPGRADE.md) when updating.

Your project supplies the Vue pages, Blade root, and Vite build. You can
enable guest server-side rendering and connect project forms to supported
native Craft actions. The [first-page quickstart](https://github.com/robuust/craft-inertia/blob/main/docs/QUICKSTART.md) walks
through the setup. Its optional scaffold and diagnostics require 1.0.2.
Contact Form has a [separate optional integration](https://github.com/robuust/craft-inertia/blob/main/docs/CONTACT_FORM.md). Full
account flows and generated TypeScript props are outside the 1.0 scope.

## Installation paths

**Control panel.** When the listing is available, open **Plugin Store** in
a compatible Craft 6 development environment with admin changes and updates
enabled. Find **Craft Inertia** by Robuust and choose **Try**, or **Install**
if offered for your license.

**Composer.** For a fresh installation, use an existing Craft 6 project that
allows its prerelease dependencies. From the project root, run:

```sh
composer require 'robuust/craft-inertia:^1.0' --with-all-dependencies
php artisan craft:plugin:install craft-inertia
```

**After installing by either path**, run
`php artisan vendor:publish --tag=craft-inertia-config` and continue at
[quickstart step 2](QUICKSTART.md#2-set-up-the-public-blade-root).

## Compare with the submitted application

When authorized to review the submission, compare the listing details and
copy above. Record approval and prices from Craft Console. Check the
submission for these screenshots, captured from the same working demo entry:

1. Craft section settings showing the native URI format and the
   `inertia:Site/Home` template.
2. The published Vue page with selected fields rendered at its native URL.
3. Craft’s native preview with a draft edit visible in the Vue page.

Use the actual 1.0.2 release date in the changelog heading when tagging.

After approval, verify the public listing’s developer, package, Craft
compatibility, and version against Packagist and the changelog. In a clean
Craft 6 consumer, verify both installation paths and follow the quickstart
through the first native entry. Record the results in
[#27](https://github.com/robuust/craft-inertia/issues/27).
