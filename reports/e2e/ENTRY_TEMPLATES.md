# Native entry-template rendering — 2026-11-11

The plugin renders an entry's native URL when its section Template is exactly
`inertia:Component/Name`. A generic plugin controller consumes Craft's matched
element and selected fields. It requires no project entry controller, duplicate
Laravel entry route or per-entry rendering/SSR policy. Projects retain their
Vue/layout/root/resolver/build, field selection, custom serializers and business
queries. See [the contract](../../docs/CONTENT.md) and
[installation/migration](../../docs/INSTALLATION.md).

## Fixed release and isolated inputs

- PHP 8.5.12, Craft `6.0.0-beta.1`, native schema `6.0.0.36`.
- CMS source/dist `05ce9b81be3704fb27da3a5a6670accfa49ec9a9`;
  assets source/dist `87acafef481f17c12a10170211bbccda42588dbd`.
- Genuine tagged manifests, no Composer aliases; `mcp/sdk` 0.9.0,
  Inertia Laravel 3.7.0; Node 24.15.0 and Yarn 4.14.1.
- Only disposable consumer locks move to beta.1. Native CMS/assets and published
  CP assets match the previously accepted beta.1 file manifests byte for byte.
- The minimal consumer is independently installed and seeded from the committed
  fixture. Its provider registers a public view namespace and official SSR
  exclusions for native CP/action paths. Craft's CP shares the official SSR
  gateway, so that one-time host exclusion is necessary for a public-only bundle.
  The plugin preserves host gateway callbacks, transport and shares.

## Criterion evidence

| Criterion | Evidence and result |
| --- | --- |
| Controller-free native URLs | Minimal consumer: homepage single, articles, unrelated journal schema and a native structure child URL; four project Vue components. No project entry controller or entry route. |
| Protocol and rendering | Client/SSR builds; real initial SSR, hydration, Inertia navigation/JSON, version conflict, public root, canonical URL and private/no-store headers. |
| Explicit field privacy | Scalars/nulls/dates/choices, sanitized Markdown, native assets/transforms/srcset, live relations and nested Matrix type/field selections. Internal fields and CP/global shares absent. Unsupported Users/object output fails explicitly; static custom serializer and dependency collection exercised. |
| Native authority | Disabled/future/expired/unpublished content remains unavailable; authorized draft/revision identities retained. Forged/expired/unrelated tokens confer no draft access. Native locale/site/nested paths retained. |
| Native editor | Actual CP Live Preview iframe autosaves title/body through an `inertia:` section, while an independent guest retains published content. No unhandled JavaScript errors; iframe responses are private/no-store. |
| Coexistence/lifecycle | Native Twig/Blade, API/feed/actions/CP and manual integrations retain their handlers. Exact prefix, headless/has-URLs/prior-handler/explicit-destination guards checked. Native disable/enable and explicit fallback exercised on fresh boots with cached routes/config. |
| Existing contracts | Core public PHP/content/cache/indexing/protocol suites, manual-controller editor/browser suites, neutral install and optional scalar Contact Form suites exercised on beta.1. |

The minimal consumer's PHP suite passes **40 tests / 218 assertions**. Its
browser suite passes **4 scenarios**, including native CP Live Preview.
Core public PHP passes **91 / 513**; neutral PHP **28 / 130** and browser **5**;
scalar Contact Form PHP **29 / 123** and browser **5**.
The broad existing browser run has 95 passing cases and three initial timing
failures. Cache invalidation and authenticated SSR pass unchanged in focused
confirmation. Both native form-validation cases pass after the harness waits
explicitly for the final native save/redirect, with all original content/status
assertions retained and no retries. This is combined criterion evidence, not a
claim that one uninterrupted 98-case run was green.

## Retained limitations and recovery evidence

The initial setup logs retain a missing test asset disk, stale newly saved native
section fixture, PostgreSQL temporary-server health race, and a host disk-full /
OrbStack interruption. Generated dependencies from failed task runtimes were
removed to recover disk space; locks, config, logs and DB volumes were preserved.
After restarting only task containers, their dynamically assigned ports changed;
only the disposable environment and pre-provider target guards were updated.
A fresh final consumer then installed, built and seeded successfully. HTTP
servers use `php -d opcache.enable=0` for rapid configuration-fixture changes;
there are no sleeps/retries masking stale bytecode.

Public SSR requires the official configuration and a suitable host SSR exclusion
for native CP/actions. The stock gateway is tested; custom gateways need a
cloneable implementation of official `DisablesSsr`. Third-party fields need an
explicit reviewed serializer; installing another plugin does not establish
compatibility. There is no HTTP output cache, general installer, automatic Twig
conversion, account adapter or native/vendor patch. An `inertia:`-only template
needs a deliberately configured native fallback when disabled.

Four native account regressions and Contact Form N01 remain open, with their
accepted beta.1 failure evidence retained separately. Those unaffected failures
were not patched, weakened, published or incorporated into this feature.
Full STEP-05, full AC-06/AC-07, release and production acceptance remain incomplete.
No compatible PHP line-coverage driver is available; no percentage is claimed.

Operational source/lock/build/native manifests, detailed XML/browser results,
failed setup evidence, preservation checks and cleanup are retained locally under
`craft-inertia-execution-20261109/entry-templates/`. The original Playground's 219
tracked/untracked input files, Git status, branch/head, protected account branch
and three stashes remain unchanged. No original DB/config/environment/vendor
activation or Playground commit/push was performed.
