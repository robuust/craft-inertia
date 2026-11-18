# Compatibility and release acceptance

The 1.0 scope covers native public entry pages, their URLs, sites and previews,
optional guest SSR, and native form integration. The consuming project owns the
Vue pages, Vite build, deployment, and corresponding acceptance. Record approved
versions and case-level results in [#17](https://github.com/robuust/craft-inertia/issues/17).

The public [v1.0.2 tag](https://github.com/robuust/craft-inertia/releases/tag/v1.0.2)
and the `source.reference` and `dist.reference` in
[Packagist's package metadata](https://repo.packagist.org/p2/robuust/craft-inertia.json)
resolve to `04c5d050b0c1896ad286972d8dbe034c5fce9f07`.
[Packagist package availability](https://packagist.org/packages/robuust/craft-inertia#v1.0.2)
is verified. Clean installation, upgrades, and runtime acceptance remain
unverified. Track Store approval and installation in the
[release issue](https://github.com/robuust/craft-inertia/issues/27).

## Baseline

The package declares PHP `^8.5`, Craft CMS `^6.0.0-beta.1`, Laravel
`^13.38` and Inertia Laravel `^3.7` in [composer.json](../composer.json).
The first consumer acceptance run targets the exact versions below, including
the Craft 6.0.0-beta.1 prerelease.

| Input | First acceptance target |
| --- | --- |
| PHP | 8.5.12 |
| Craft CMS and CP assets | 6.0.0-beta.1, CMS `05ce9b81be3704fb27da3a5a6670accfa49ec9a9`, assets `87acafef481f17c12a10170211bbccda42588dbd` |
| Laravel and Inertia Laravel | 13.42.0 and 3.7.0 |
| Inertia Vue and Inertia Vite | 3.11.0 each |
| Vue and Vite | 3.5.45 and 8.3.6 |
| Node and Yarn | 24.15.0 and 4.18.0 |
| Craft Inertia | Public `v1.0.2` for fresh installs and as the upgrade target. Use `v1.0.1`, `v1.0.0` and `v0.1.0-beta.3` as separate upgrade inputs. |
| Optional Contact Form | 4.0.0, in its own consumer lock |

These versions come from the existing [Craft 6 Playground](https://github.com/robuust/craft6-playground)
compatibility fixture. Its authored profiles lock a pre-1.0 plugin revision.
Update the fixture inputs to the published release before recording consumer
acceptance. Keep the core profile independent of Contact Form and other
optional plugins.

## Consumer checks

Use a fresh Craft consumer and separate existing consumers on 1.0.0,
1.0.1 and beta.3, covering enabled and disabled beta registrations. Pin
`robuust/craft-inertia:1.0.2` from Packagist and confirm the lockfile resolves
to the release commit above. Preserve exact Composer and frontend locks, runtime
versions, Craft project config, package reference, and built asset digests
alongside the results. The cases below still need consumer verification.

| Case | Observable acceptance |
| --- | --- |
| Clean install | Install the [published Packagist package](INSTALLATION.md#install-from-packagist). Continue at [quickstart step 2](QUICKSTART.md#2-set-up-the-public-blade-root) to build the client bundle. Confirm native `craft-inertia` registration, first HTML, client navigation, and the configured fallback after disable/enable. |
| Frontend setup ([#13](https://github.com/robuust/craft-inertia/issues/13)) | Preview and apply the scaffold in both a clean and an existing configured consumer. Confirm the dry run preserves the filesystem, an ordinary write preserves existing files, an explicit replacement affects only scaffolded files, and a repeat reports unchanged files. Verify provider discovery and the doctor's plugin status, view and component paths, dependency versions, build artifacts and production SSR connectivity reports. |
| Upgrade from beta.3 ([#24](https://github.com/robuust/craft-inertia/issues/24)) | Follow the [upgrade guide](UPGRADE.md#from-beta3) from enabled and disabled beta.3. Confirm removal of the old registration while beta.3 is still installed, installation under `craft-inertia`, restoration of the previous enabled state, project-config propagation, retained content and frontend files, mapped rendering with the chosen field selection, and rollback with matching backup and sources. |
| Native routing and preview | Check home, nested and detail URLs across sites, existing native routes and explicit destinations, authorized draft/revision identity, and unauthenticated access to unpublished content. Confirm native statuses and redirects and CP/action isolation. |
| Upgrade from 1.0.0 and 1.0.1 | Upgrade each version separately and verify that the existing `craft-inertia` registration and enabled state persist. Verify the frontend after deploying explicit field lists or the all-fields opt-in with the new code. Roll back to the matching prior lock and configuration. |
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
  are outside the 1.0 support scope. Version 1.0.2 still needs the native
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
