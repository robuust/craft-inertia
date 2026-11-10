# STEP-04: forms and private file operations

STEP-04 implements REQ-04/08 and AC-04 using native Craft owners and the official
Inertia APIs. The plugin adds scoped session flash/message-only error handling;
the local Playground owns the example pages, actions and file policy. The
[operation contracts](../../docs/FORMS.md) name the supported operations and
transport choices. No content schema or upload framework is added to the package.

## Observable behavior

- A project Vue `<Form>` submits directly to native `entries/save-entry`, with
  numeric native IDs, native validation, a named error bag and encrypted return
  target. Native old input is returned in page props; empty input remains the
  framework's `null`. Successful edits are observed in the public overview and
  restored in the synthetic fixture.
- A separate project search form uses Laravel validation in `articleSearch`,
  native notices and a current-site redirect. Errors remain with their form;
  subsequent submissions clear stale failures and notices expire normally.
- Native `notice`, `success` and `error` session keys become official Inertia
  flash data without clearing host shares/flash. A native error without field
  errors supplies `message` in the submitting form's bag, preserving other bags,
  so Inertia cannot call that redirect a success.
- Project multipart uploads delegate to native `AssetUploads::authorize()` and
  `complete()` and return native JSON intact. `useHttp` owns input/errors/
  progress/processing. Message-only 422 failures, other HTTP/network failures
  and native 200 filename conflicts cannot display success. Only an accepted
  upload resets the file input and refreshes permitted file metadata.
- The configured private-volume download uses asset IDs across native sites,
  native `viewFile` authorization and the native streaming controller. Guests,
  users without permissions, invalidated sessions and wrong destinations cannot receive file bytes
  or metadata. Native session invalidation and the existing 60/minute upload
  initiation limiter remain enforced. Accepted JSON/page/byte responses stay
  private/no-store.

The entry/search and multipart upload flows are exercised on primary and `/nl`
contexts. Native upload-session metadata, size/extension validation, ownership,
completion failure and cancellation are also exercised through their actual
HTTP endpoints. Native TUS byte transfer and remote storage are not claimed.
The multipart bridge uses the same native file owner without recreating TUS.

## Verification and provenance

| Check | Result |
| --- | --- |
| Approved dependency identity | CMS `0506c32b44efbe3e7f5fded46a7c1d39de699d5b`, assets `17973259d5727a1e50a1fc85dd90566b889407af`; source root lock, full installed trees and native published assets match STEP-03 exactly. |
| Fresh isolated build | Client and SSR builds pass; Node `24.15.0`, PHP `8.5.12`, Laravel `13.40.0`, Inertia Laravel `3.7.0`, Vue adapter `3.11.0`. No new dependencies. |
| PHP integration | 19 tests / 110 assertions pass: public/form middleware plus existing content/query contracts. No PHP coverage driver; explicit `--no-coverage`, no PHP coverage claim. |
| Vue suite | 42 tests pass, required full-suite 100% line coverage met; new Files page has 100% statements/branches/functions/lines. |
| Prior routing/content/sites | All 52 predecessor browser cases pass in the serialized run. Native View/preview and HTML/JSON privacy are included; no new full-editor autosave claim. |
| STEP-04 browser | All 11 cases pass together in `correction-browser/`, including three independently invalidated sessions and 60 accepted initiation attempts followed by HTTP 429. Zero unhandled browser errors. |
| Static checks | TypeScript, Prettier, scoped ESLint/PHP CS Fixer, PHP syntax, plugin Composer strict validation and whitespace checks pass. |
| Independent review | Recorded in the execution run's `step04/review.md`; publication requires its final PASS. |

Evidence is in
`/Users/joostwaaijer/.codex/agent-team/runs/craft-inertia-execution-20261109/step04/`.
`final-prepare/` retains fixed manifests, root locks, full dependency/published
asset hashes and build logs. `final-php-tests.log`, `vue-tests.log`, copied Vue
coverage, `serial-browser/` and `correction-browser/` bind
checks to source snapshots. Aggregate/incremental Playground patches, every
current file hash and the plugin fingerprint are recorded alongside them.

These are **63 unique passing browser cases across separate runs**, not a
single green 63-case execution: 52 predecessor cases and the final 11-case
STEP-04 run. Historical failures remain preserved. Initial
runs exposed string IDs sent to a strictly typed native action and a localized
asset lookup; the project integration corrects both. Test corrections included
exact selectors, localized search/error text and native empty-string-to-null old
input. An overlapping PHP/browser run failed one alternate-schema case; that
case and all 52 regressions subsequently pass with shared-runtime checks
serialized. The native login limiter was reached by repeated development runs;
the harness now reuses real authenticated test sessions rather than weakening
or disabling the limiter. Missing `POC_FIXTURES_PATH` in an initial PHP invocation
was corrected for the passing integration run. No assertion is suppressed,
marked expected or waived.

The independent review found that the project file routes omitted native session
invalidation and upload throttling. Correction cycle 1 adds the existing
`AuthenticateCraftSession` middleware and `throttle:60,1`. Three separate real
native sessions are established before changing a synthetic user's password;
each private operation returns 401 afterward. A dedicated native-permitted user
gets 422 for the first 60 empty upload attempts and 429 on attempt 61. All eleven
affected browser cases pass with these corrections. Prior PHP/Vue/build and
52 public regression evidence remains applicable: their inputs and application
paths are unchanged. Corrected route source, native middleware inventory and
request evidence are retained in the execution snapshots; no native owner was
patched.

## Delivery boundary

The fresh plugin branch is `feature/public-forms-files`, based on merged STEP-03
`0d08321c99e90ee781ab630d51a67ebd235f74b6`. The sole plugin PR is handed to the
coordinator for its exact-head merge gate. This slice does not merge or start
STEP-05. There is no CI workflow or meaningful changelog in this personal repo;
no Robuust team review or auto-merge setting is invented.

Playground remains local and **uncommitted** on `feature/native-inertia-pages`
at `f7dd95da555f704d49aa590f7fa60bf7d24f1731`; no commit, push or PR is made there.
All 22 prerequisite files were verified before edits and preserved, with only
scoped changes recorded incrementally. Native synthetic users/assets live only
in the guarded isolated PostgreSQL fixture. The original database, project YAML
and production storage remain untouched. No database upgrade, external messages,
production deployment, complete account lifecycle or universal action/plugin
compatibility is claimed.
