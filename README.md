# Craft 6 + Inertia + Vue proof of concept

A local Craft plugin with a public entry overview, an entry page and a Live
Preview target. It uses Craft for content and preview authorization, the official
Laravel adapter for Inertia responses, and Vue 3 for rendering.

The agreed product direction is a **reusable plugin for complete Craft site
frontends**, with project-owned Vue pages and Craft-owned backend behavior. The
[frontend scope and roadmap](docs/FRONTEND.md) describe the required capabilities
and the steps beyond this demonstration. Those capabilities are not yet
implemented by this proof of concept.

Start with the [project initialization](docs/PROJECT.md) for sources, current
coverage and readiness. The [glossary](CONTEXT.md) and
[domain and response contracts](docs/DOMAIN_MODEL.md) define the shared terms
and ownership used by the roadmap.

This is an integration proof of concept, not a production plugin. Its first
integration target is `robuust/craft6-playground`, currently locked to Craft
`6.0.0-alpha.20`, `inertiajs/inertia-laravel` `3.7.0`, and Laravel `13.40.0`.

## Current proof of concept

- `GET /inertia-poc`: the latest 20 live entries from the configured section.
- `GET /inertia-poc/entries/{canonicalId}`: an entry page, or a Vue 404 page.
- Navigation between these pages uses Inertia's `Link` component.
- The Craft editor receives an additional **Inertia + Vue proof of concept**
  preview target for entries in that section. Existing targets remain available.
- Live Preview uses the placeholder element installed by Craft after validating
  the preview token. Request parameters do not grant access to drafts.
- The public overview always ignores preview placeholders. Public detail requests
  use Craft's normal live-entry query unless Craft supplied a preview placeholder
  for that exact canonical entry and site.
- Title, date and optional Plain Text `summary` / `body` fields become explicit
  page props. Field handles are configurable. Missing fields render as empty text.
- The plugin owns its Blade root view, Vue app and published assets. It clears
  inherited Inertia shared props and disables SSR for its own routes so the host
  app's authenticated props and SSR bundle are not used by these pages.

The initial target is one frontend site whose base URL is at the origin root.
Multisite with subdirectory URLs, canonical entry URL routing, rich text, Matrix
blocks, forms, pagination and SSR are outside this first proof of concept.

## Build this package

Requirements: Node 22.12+ or Node 24, and PHP 8.5 for the consuming Craft app.

```sh
npm ci
npm run build
```

This writes a Vite manifest and hashed assets to `public/build`. Build before
installing the plugin so Craft can publish those files. Generated assets and
dependencies are ignored by Git; `package-lock.json` is retained.

## Install in Craft 6 Playground

Run these commands from the Playground checkout, or its PHP container. This
package must be available at a path visible to that environment. With the current
OrbStack Docker setup, a sibling host directory is not automatically mounted in
the PHP container. Mount this package at `/app/user/packages/craft-inertia-poc`
in each consuming PHP service (`setup`, `web` and `worker`) before using the
relative path below. Copying the package into the host checkout alone is not
enough: the Playground does not mount its entire working directory.

Merge this entry into the Playground's Composer `repositories` list:

```json
{
  "type": "path",
  "url": "packages/craft-inertia-poc",
  "options": {
    "symlink": true,
    "versions": {
      "robuust/craft-inertia-poc": "0.1.0"
    }
  }
}
```

Then install and enable the plugin through Craft:

```sh
composer require robuust/craft-inertia-poc:^0.1 --no-interaction
php artisan craft:plugin:install inertia-poc --no-interaction
php artisan vendor:publish --tag=inertia-poc --force --no-interaction
php artisan vendor:publish --tag=inertia-poc-config --no-interaction
php artisan route:list --path=inertia-poc -v
```

Do not add the plugin to `bootstrap/providers.php` or Composer's
`extra.laravel.providers`. Craft owns plugin discovery and respects whether the
plugin is installed and enabled.

The host does not need to import these Vue pages into its own frontend, change
its Vite configuration or stop its existing SSR service. On package changes,
rebuild here and re-publish the `inertia-poc` assets in the Playground.

## Prepare content

1. Create a section with handle `articles`, or set `section` in the published
   `config/inertia-poc.php` to an existing section's handle.
2. Assign an entry type with a title and, optionally, Plain Text fields with
   handles `summary` and `body`. Enable multiline input on `body`.
3. Publish two entries on the site's current language, with post dates in the
   past. The overview can also render without the optional fields.
4. Open `/inertia-poc` on the frontend origin. The overview links to the plugin's
   own entry URLs; it does not change the section's canonical URLs.

The plugin does not create sections, fields, entries or database migrations.

## Try Live Preview in the Playground

1. Open one of those entries in Craft and open Live Preview.
2. Select **Inertia + Vue proof of concept** from the preview target menu.
3. Change the title, summary or body without publishing. Craft saves its
   provisional draft and refreshes the preview iframe. The Vue page should show
   the changed draft and a preview notice.
4. In a separate browser tab, open the same `/inertia-poc/entries/{canonicalId}`
   URL without preview parameters. It should still show the published version.
5. Repeat with a saved named draft and an unpublished draft. Confirm they render
   only with Craft's valid preview URL. An unpublished entry must be unavailable
   publicly.
6. A missing/invalid token or a forged preview marker must never reveal the draft.
   After publishing, a normal full-page visit should show the newly published data.

Craft owns draft persistence, tokens and iframe refreshes. The plugin does not
implement a separate preview endpoint, token checker or polling loop. All plugin
responses have `Cache-Control: private, no-store` and `X-Robots-Tag: noindex,
nofollow` because they are experimental and may contain preview data.

## Checks and integration results

```sh
composer validate --strict
npm run format:check
npm run build
```

PHP syntax, TypeScript, formatting and asset compilation can be checked in this
package. A real-browser E2E run in an isolated Craft 6 Playground recorded
**26 passed and 1 failed**. Installation, entry pages, Inertia navigation, draft
access boundaries and separation from the host control panel were exercised.
Automatic Live Preview passed its functional assertions, but its test failed
because of an unhandled JavaScript error also reproduced in native Craft/Twig
preview without loading the plugin bundle.

The [full E2E report](reports/e2e/REPORT.md) includes the environment, all
scenarios, evidence and remaining limitations. The run covers the current
demonstration, not the reusable frontend capabilities in the roadmap.

The existing test setup is in `tests/e2e`. Its preparation command creates a
separate Playground snapshot and dedicated database; it does not use the
original Playground database. See the report before preparing another run.

## Source contracts

- [Craft 6 plugin lifecycle and discovery](https://craftcms.com/docs/6.x/extend/avenues.html)
- [Craft 6 plugin routes and middleware](https://craftcms.com/docs/6.x/extend/http.html)
- [Craft alpha.20 preview token handling](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Http/Middleware/HandleTokenRequest.php)
- [Craft alpha.20 preview placeholder preparation](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Http/PreviewRequestPreparer.php)
- [Craft alpha.20 preview targets](https://github.com/craftcms/cms/blob/6.0.0-alpha.20/src/Element/Concerns/HasPreviewTargets.php)
- [Inertia 3 client setup](https://inertiajs.com/docs/v3/installation/client-side-setup)
