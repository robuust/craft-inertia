# Release Notes for Craft Inertia

## 1.2.0 - 2026-11-26

- Added `deferredEntryFields` to load expensive fields on native entry pages after the first render, with optional groups that load in parallel.

## 1.1.1 - 2026-11-26

- Generate `RelatedEntry[]` for entry relation fields such as Reverse Entry Relations, and use `JsonValue` for fields without a known serialized type.

## 1.1.0 - 2026-11-18

- Added `craft-inertia:types` to generate TypeScript page props from the applied Craft schema and check declarations for drift.

## 1.0.2 - 2026-11-18

> [!IMPORTANT]
> Review the [upgrade guide](https://github.com/robuust/craft-inertia/blob/main/docs/UPGRADE.md) before updating an existing installation.

- Made custom fields opt-in for native entry pages. An entry with no matching `entryFields` rule keeps its base properties and exposes no custom fields. This also applies to related entries and Matrix blocks.
- To retain the previous full-layout fallback, explicitly set `entryFields` to `['*' => '*']`. This includes fields hidden by editor layout conditions and fields added later. Use entry-type field lists to expose only the fields your frontend needs.
- Added opt-in frontend scaffolding and read-only setup diagnostics for Craft consumers.

## 1.0.1 - 2026-11-18

- Adopted the Craft License for paid distribution.

## 1.0.0 - 2026-11-18

- Released Craft Inertia for Craft CMS 6 with opt-in Vue rendering at native entry URLs and support for native previews.
- Changed the Craft plugin handle from `inertia` to `craft-inertia`. Beta users must remove the old registration and install the new handle. Follow the [1.0.0 handle upgrade steps](https://github.com/robuust/craft-inertia/blob/5448100e96cda7267ed25b881eff8e44c54165e1/docs/INSTALLATION.md#upgrade-from-the-inertia-handle).

## 0.1.0-beta.3 - 2026-11-17

- Completed the native entry page integration with entry-type field selection and shared Head rendering for the Blade root and Vue pages.

## 0.1.0-beta.2 - 2026-11-17

- Added a shared public renderer for native entry pages and project controllers, plus project extension points for page props, field serializers and responsive image profiles.
- Added native action form props and an optional Contact Form 4.0 integration example.

## 0.1.0-beta.1 - 2026-11-12

- First public Composer beta for opted-in Craft 6 entry pages using a project-owned Inertia and Vue frontend.
