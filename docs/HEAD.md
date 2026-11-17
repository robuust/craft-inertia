# Native page metadata

Native `inertia:` pages expose `entry`, `site`, `preview` and `head`. There is no
built-in `seo` prop or third-party plugin integration. Entry title, URL and
selected editorial fields remain on `entry`. The plugin supplies a generic
Head contract, with one title, canonical link and effective robots directive.

## Project metadata provider

With no configuration, metadata uses the matched entry title and URL, without
query parameters or fragment, and the renderer's robots policy. To add calculated
metadata, register a cacheable public static callback in `config/craft-inertia.php`:

```php
'head' => [\App\PublicFrontend\PageMetadata::class, 'props'],
```

The project callback receives `PublicPageContext`: the actual matched entry,
current site, request and robots ceiling. It may return any of these keys:

| Provider key | Input |
| --- | --- |
| `title` | Plain string. Omission keeps the entry title. |
| `meta` | Ordered attribute maps, such as `[['name' => 'description', 'content' => '...']]`. |
| `link` | Ordered attribute maps, such as canonical/alternate links. Omission keeps the entry canonical; `[]` removes it. |
| `jsonLd` | Keyed JSON-compatible objects, **before** JSON encoding. |

`head.title` is the **complete document title**, including any site name or other
formatting selected by the project provider. In both client and SSR
`createInertiaApp()` setup, omit the `title` callback or use an identity callback.
Remove an existing callback that adds a prefix/suffix or otherwise changes titles;
move that formatting into the project provider instead. The packaged Vue Head
passes this complete title to Inertia, while the Blade fallback renders it
directly. A transforming Inertia title callback would make the SSR/hydrated title
differ from initial HTML when SSR is disabled or unavailable.

Attributes must have valid HTML names and string or boolean values. False is
omitted and true becomes an empty-string presence attribute, so Blade and Vue
have the same HTML semantics. Return only deliberately selected
public values. The callback owns any external dependency checks, calculation,
site/preview semantics and lifecycle. The plugin imports, detects and configures
no third-party metadata plugin. Extra project props remain available through
`publicProps`, but `head` is reserved so all native metadata passes through the
same policy and serialization boundary. Callback errors propagate.

## Packaged Head components

Add one Vite alias to the installed Composer package's Vue source:

```js
// Alongside the project's existing resolve.alias entries:
'@craft-inertia': fileURLToPath(new URL('./vendor/robuust/craft-inertia/resources/js', import.meta.url)),
```

Import `fileURLToPath` from `node:url` if needed. Render the component once in the
native page's shared Vue layout:

```vue
<script setup>
import PageHead from '@craft-inertia/PageHead.vue';
</script>

<template>
  <PageHead />
  <slot />
</template>
```

In the public Blade root, replace the existing Inertia head element and its
metadata fallback with:

```blade
<x-craft-inertia::head :page="$page" />
```

The Blade component wraps the official `<x-inertia::head>`: an available SSR
response supplies its head; otherwise the plugin renders the same page metadata
on the server. Use both packaged components together. Keep static viewport,
icons, fonts and site assets in the root. Remove any second title/canonical/robots
renderer. The plugin supplies no layout, styling, page order or consent UI.

Inertia Head manages client navigation and removal of old tags. Titles and
attributes are escaped. JSON-LD is encoded with HTML-sensitive characters escaped
before it becomes script text. Executable scripts, consent, analytics and script
placement remain project-owned.

## Normalized page contract and response policy

`head` contains `title`, `meta`, `link`, `jsonLd` and `robots`. `jsonLd` becomes an
ordered list of `{key, content}`, with HTML-safe JSON text in `content`; this is
the packaged components' script sink. The plugin emits exactly one generic
robots tag and at most one canonical link. Repeated Open Graph properties retain
their order.

The renderer retains authority over preview, auth, token, form state, site and
publication eligibility. Provider metadata can only restrict that policy further.
`none` means `noindex, nofollow`; `noindex` and `nofollow` override their positive
counterparts, while additional directives such as `noarchive` are retained.
`indexifembedded` is removed whenever the combined policy contains `noindex`,
so embedding cannot provide an exception to that indexing denial.
`head.robots`, its meta tag and the final HTTP header use the same effective
policy. Adapter version conflicts and other non-200 responses remain
non-indexable. Default-deny indexing and Craft `disallowRobots` still apply.

The provider runs inside the isolated public render boundary, once for HTML,
SSR-unavailable HTML and JSON, including partial visits. The official adapter
then applies partial-prop filtering. No metadata or context is cached by the
plugin or retained between renders. A `publicProps` callback still receives the
renderer's original robots ceiling; normalized `head.robots` may restrict it.
Bespoke `PublicRenderer` responses retain their explicit contract; native Head
props are not added automatically.

Before consumer rollout, manually check calculated metadata, SSR on/off,
navigation/partial visits, authorized previews and multiple sites against that
consumer's locked dependencies. This package contains no application or frontend
harness; passing lint/build checks does not establish consumer runtime acceptance.
