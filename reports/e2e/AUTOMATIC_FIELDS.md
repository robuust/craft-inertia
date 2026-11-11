# Automatic entry fields on Craft 6 beta.1

The 2026-11-11 human instruction supersedes the explicit field selector introduced
in PR #11. Native `inertia:Component` templates now expose every custom field
attached to the matched entry's layout, under `entry.fields.<nativeHandle>`.
The native section/template and entry-type layout YAML already carry this setup;
no additional admin selector, plugin YAML schema or PHP field list is needed.

All attached fields, including editorial internal notes and conditional layout
fields, are page data. The old `pages[component].fields` configuration is ignored.
Related entries and Matrix include their own complete layout fields; circular
references and ten relation levels stop further expansion while retaining base
identity. The base entry shape and official Inertia response boundaries stay intact.

Assets use original native URLs/dimensions (`srcset: null`); the previous
selection-specific transform/srcset options are removed. Dates, choices,
sanitized Markdown and links retain native presentation data. Other fields use
their own native `serializeValue()` contract, including Users relation IDs,
JSON/Color/Money and custom field serializers. Finite JSON values, `stdClass`
and `JsonSerializable` are accepted; arbitrary object dumps are rejected.

## Current verification

The released isolated entry-template consumer was reused with its retained,
task-owned PostgreSQL volume. The original Playground was never activated or
modified. Installed Composer metadata still pins actual `6.0.0-beta.1` source
and dist references, with no version aliases:

- CMS: `05ce9b81be3704fb27da3a5a6670accfa49ec9a9`.
- CMS assets: `87acafef481f17c12a10170211bbccda42588dbd`.

| Check | Result |
| --- | --- |
| Full native consumer PHP suite | 42 tests, 227 assertions; no errors, failures or skips. |
| Real Chrome suite | 4 scenarios; all passed, zero retries/skips/flaky results. |
| Automatic field contract | No component config; every root/related/Matrix layout field; newly registered layout fields; native JSON including empty objects, Color, Link, Money/null; custom field serialization; object rejection; circular relation termination. |
| Existing native behavior | Publication/status/site/draft/revision/token boundaries, dependency collector, CP/global share isolation, host SSR factory/gateway restoration, cached routes and native disable/enable/fallback. |
| Browser behavior | Homepage SSR/hydration, real Inertia JSON/navigation with automatic fields, structure/second site, private draft/revision and actual native CP Live Preview without publication. |
| Static checks | Changed PHP syntax, Composer strict validation, TypeScript, scoped Prettier and diff whitespace passed. |

No frontend runtime source changed, so the existing matching client/SSR bundles
were reused. PHP ran on 8.5.12 and SSR on Node 24.15.0. No PHP coverage percentage
is claimed. Earlier unsuccessful fixture attempts are retained outside the
repository; they concerned unregistered field handles, required native field
settings and flushing modified native project config between test applications.
No framework/vendor patches or suppressed assertions were used.

Current raw evidence is in the coordinator's established external run under
`automatic-entry-fields/`: `runtime.json`, `phpunit-final.xml`,
`browser/results.json`, preservation records and source manifest.

## Limits

This is bounded beta.1 verification. The four native account regressions,
Contact Form N01 and full STEP05/AC06/AC07 remain open; those unrelated flows
were not rerun or claimed fixed. Existing explicit project integrations retain
their own prop contracts. Custom fields depend on their native serialization
implementation; this is not blanket compatibility acceptance for every plugin.
The original local Playground remains uncommitted and has not been migrated.
