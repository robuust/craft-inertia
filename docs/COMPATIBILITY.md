# Compatibility and release acceptance

The 1.0 scope covers native public entry pages, their URLs, sites and previews,
optional guest SSR, and native form integration. The consuming project owns the
Vue pages, Vite build, deployment, and corresponding acceptance. Record approved
versions and case-level results in [#17](https://github.com/robuust/craft-inertia/issues/17).

Versions 1.0.0 and 1.0.1 are available on
[Packagist](https://packagist.org/packages/robuust/craft-inertia).
The [v1.0.0 tag](https://github.com/robuust/craft-inertia/releases/tag/v1.0.0)
contains the new Craft plugin handle. The
[v1.0.1 tag](https://github.com/robuust/craft-inertia/releases/tag/v1.0.1)
adopts the Craft License. The 1.0.2 work requires explicit public field
selection. Track Store approval in the
[release issue](https://github.com/robuust/craft-inertia/issues/27).

## Baseline

The ranges in [composer.json](../composer.json) describe what Composer can
resolve. The planned acceptance run targets the versions below. Craft 6.0.0-beta.1
is a prerelease. The 1.0.2 package and frontend combination awaits acceptance.

| Input | First acceptance target |
| --- | --- |
| PHP | 8.5.12 |
| Craft CMS and CP assets | 6.0.0-beta.1, CMS `05ce9b81be3704fb27da3a5a6670accfa49ec9a9`, assets `87acafef481f17c12a10170211bbccda42588dbd` |
| Laravel and Inertia Laravel | 13.42.0 and 3.7.0 |
| Inertia Vue and Inertia Vite | 3.11.0 each |
| Vue and Vite | 3.5.45 and 8.3.6 |
| Node and Yarn | 24.15.0 and 4.18.0 |
| Craft Inertia | Pinned 1.0.2 candidate, public `v1.0.1`, `v1.0.0` and `v0.1.0-beta.3` as upgrade inputs |
| Optional Contact Form | 4.0.0, in its own consumer lock |

These versions come from the existing [Craft 6 Playground](https://github.com/robuust/craft6-playground)
compatibility fixture. Its authored profiles lock a pre-1.0 plugin revision.
Update the fixture inputs to the exact candidate before using the results for
release acceptance. Keep the core profile independent of Contact Form and other
optional plugins.

## Consumer checks

Use a fresh Craft consumer and separate existing consumers on 1.0.0,
1.0.1 and beta.3, covering enabled and disabled beta registrations. Preserve
exact Composer and frontend locks, runtime versions, Craft project config, candidate
reference, and built asset digests alongside the results. Use a remotely
installable candidate for the distribution check. A local artifact can validate
behavior ahead of publication. Record its digest and repeat installation with
the published package when it becomes available.

| Case | Observable acceptance |
| --- | --- |
| Clean install | Install the pinned package using the [candidate instructions](INSTALLATION.md#try-a-pinned-candidate). Continue at [quickstart step 2](QUICKSTART.md#2-set-up-the-public-blade-root) to build the client bundle in a clean consumer. Confirm first HTML and client navigation, the native registration, and the configured fallback after disable/enable. |
| Frontend setup | Preview and apply the scaffold in a clean consumer, then repeat in a configured consumer with an existing frontend entry. Confirm the dry run preserves the filesystem, an ordinary write preserves existing files, and a repeat reports unchanged files. Run the doctor for the mapped entry and confirm missing files and production SSR connectivity are reported as expected. |
| Upgrade from beta.3 | Follow the [upgrade guide](UPGRADE.md) from enabled and disabled beta.3. Confirm removal of the old registration under beta.3, the new registry entry, previous enabled state, project-config propagation, retained content and frontend files, and rollback with matching backup and sources. |
| Native routing and preview | Check home, nested and detail URLs across sites, existing native routes and explicit destinations, authorized draft/revision identity, and unauthenticated access to unpublished content. Confirm native statuses and redirects and CP/action isolation. |
| Upgrade from 1.0.0 or 1.0.1 | Verify that the existing `craft-inertia` registration and enabled state persist. Verify the frontend after deploying explicit field lists or the all-fields opt-in with the new code. Roll back to the matching prior lock and configuration. |
| Public fields | Compare initial page data and Inertia JSON for projected root entries, Entries relations and Matrix children. Check unlisted and newly added entry fields, exact type precedence over the wildcard, an explicit empty selection, and the all-fields opt-in in 1.0.2. Verify selected values retain their serializers and image profiles. Review the output of selected native composite fields. |
| SSR and session boundaries | Check guest HTML, hydration, Inertia navigation and the fallback with SSR unavailable. Verify private preview, authenticated and form responses and the next guest request. Confirm SEO metadata, indexing policy, response cache headers and the official asset-version reload. |
| Native forms and uploads | Start on a fresh visit, then navigate in via Inertia. Confirm CSRF/session initialization, a successful native submission, validation failure, expected redirects and flash. Verify representative multipart handling and access for any private file published by the consumer. Follow [Forms](FORMS.md). |
| Optional Contact Form | Check form errors, nested-message validation, rate limit and local mail handoff against 4.0.0 in a separate consumer. Use [Contact Form](CONTACT_FORM.md) for the expected integration contract. |

Collect pass, fail and unverified results for each case, including the returned
status, key headers and the intended fields. Keep evidence private when it
contains preview URLs, tokens or form payloads. Use run-owned infrastructure
and retain the locks and evidence before cleaning it up.

## Known limits

- The historical Craft beta.1 account control found four native regressions.
  Activation left its token reusable, and native set-password accepted the
  used token. Two required-2FA actions returned enrollment HTML in place of
  their JSON setup and validation responses. Full native frontend account flows
  are outside the 1.0 support scope. The 1.0.2 candidate still needs the native
  routing and isolation checks. See the
  [historical account report](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP07C.md).
- The old Contact Form development baseline failed on unlisted nested
  messages. [Contact Form 4.0.0](https://github.com/craftcms/contact-form/releases/tag/4.0.0)
  includes the source fix. Its runtime result in the combined integration
  remains to be recorded.
- Production acceptance belongs to each consumer. Keep matching client and SSR
  bundles, their manifest and referenced assets available across deploys.
  Supervise the private SSR process when enabled and verify fallback when it
  stops. Apply native project config with matching code. Rebuild affected
  caches and restart long-lived workers after updates. Rehearse rollback with
  matching code, database, config and assets before restoring traffic.
