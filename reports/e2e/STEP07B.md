# STEP-07B: optional native Contact Form

**Result (2026-11-10):** the bounded scalar contact flow passes on the exact
human-selected official source. The combined supported PHP suite has **29 tests,
123 assertions, zero failures/skips**; the real-browser suite has **5 passes,
zero failures/skips/retries**. A separate native nested-message regression has
**one real failure**, returning 500 instead of validation. It remains open and
is not counted as accepted behavior. The [versioned capability matrix](../../docs/CONTACT_FORM.md)
is the support boundary, not general Contact Form compatibility or full AC-07.

## Baseline and implementation

Work starts from live merged STEP-07A PR [#8](https://github.com/robuust/craft-inertia/pull/8),
merge `42c10aaca6c7f820e2dc6cb3089252401a6fa2e5`, on fresh
`feature/contact-form-compatibility`. The original Playground stays read-only;
the protected account branch and all three partial STEP-05 stashes stay intact.
No native account defect is closed by this slice.

| Component | Exact evaluated basis |
| --- | --- |
| Optional owning plugin | `craftcms/contact-form` `4.x-dev`, source/dist `6f3b6deed57d909ab024190a53d93dedc206e13f` |
| Contact Form unmodified manifest SHA-256 | `9702e84ddb88004c05e4336d9f590955c76c529347c5cf2438e954ed88b04c2b` |
| Craft CMS | `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` |
| Craft CP assets | `17973259d5727a1e50a1fc85dd90566b889407af` |
| Laravel / Inertia Laravel | 13.40.0 / 3.7.0 |
| Inertia Vue / Vue / Vite | 3.11.0 / 3.5.45 / 8.3.6 |
| PHP / Node / Yarn / PHPUnit | 8.5.12 / 24.15.0 / 4.14.1 / 13.4.6 |
| Original approved Composer lock SHA-256 | `c37b365487d36e9e4dd4e0f5ae32737099ce3e095d4fec723da321a5e58e6b10` |
| Optional runtime Composer lock SHA-256 | `c55aef0c89e9438230ee71d1db4505ee5628df3c33ed1c6ad672b96eb22829c6` |

These are official unreleased development sources. The existing CMS development
alias satisfies the candidate's unchanged `^6.0.0` requirement. No stable 4.x,
released alpha.20, beta-only branch substitution, dependency constraint bypass,
CMS/assets upgrade or broad Composer-range acceptance is claimed.

The optional profile extends the accepted clean-installation harness with a small
project overlay. It has its own fresh empty DB, runtime and Compose project.
Contact Form is added only to that isolated consumer. Every other Composer
package record and the frontend lock are unchanged; native CMS/assets bytes
match the accepted STEP-07A evidence, and installed Contact Form bytes match the
exact official archive. Published native assets also match. Core plugin source,
config and Composer manifest are unchanged.

The project controller only renders the page, encrypts a success URL and selects
explicit old-input props. One official `<Form>` owns submission/errors/processing.
POST reaches the unchanged native `SendController`, `SubmissionRuleset`, settings,
rate limiter, flash/redirect and system-message mailable. A project response
header policy protects the selected paths, including exceptions, without
converting their native responses or removing middleware. There is no generic
JSON adapter, copied native controller/validator, parallel settings model or
frontend error state machine.

## Criterion-level results

| Criterion | Result and evidence |
| --- | --- |
| B01: guest opted-in Vue contact page | PASS. PHP `testContactPagesNeverReachSsrOrExposeSettingsAndSubmissionData`; browser guest flow. Native names, private HTML/JSON and no recipient/settings projection. |
| B02: invalid required data, native errors, no mail | PASS. PHP native validation test and browser invalid email/empty message. Native global flash and both field errors; capture unchanged. |
| B03: intended input retained, correction and native success | PASS. Browser guest flow keeps name/subject during the failed visit, focuses the linked summary, disables the processing button, corrects the fields and follows the native redirect to the native success notice. Reload/durable draft persistence is not promised. |
| B04: recipient/reply-to/subject/body and actual native handoff | PASS for local capture. PHP native mail test injects `to` and `toEmail`; the MIME recipient remains server-owned. Native prefix, reply-to and body are present after real Twig/system-message and multipart rendering. Browser success creates another real capture. |
| B05: native optional name/subject | PASS. Separate PHP success request omits both; the native default subject and body reach capture. |
| B06: absent/invalid CSRF | PASS through the HTTP server. Both tokenless/invalid requests without trusted-origin metadata return native 419 and produce no mail. Native Laravel same-origin protection remains unchanged. |
| B07: native configured throttling | PASS. PHP and HTTP browser probes reach the configured three-attempt limit; fourth valid-CSRF request gets 429/Retry-After and no mail. |
| B08: separate visitor sessions | PASS. Independent browser contexts: visitor A retains its failed input; visitor B receives empty fields and no A input/flash in HTML or Inertia JSON. |
| B09: sensitive response isolation | PASS within this fixture. Page HTML/JSON, native redirects, 419/429 and the native-limit 500 retain private/no-store/noindex. SSR is disabled with the official API; an HTTP stray-request guard proves no SSR dispatch. No JSON-LD or public data-cache path is registered. |
| B10: attachments disabled | PASS. Native settings reject an uploaded file, with field errors and unchanged capture. Enabled attachments are unverified. |
| B11: database, mail and local-storage isolation | PASS after review correction. The accepted 13 DB/URL/cached-config probes and two mail probes remain passing. Six new console/HTTP probes establish local cache/session defaults, named-store removal, limiter/lock selection and fixed file paths before providers, including inherited Redis and cached foreign paths. A feature test verifies the real file stores after boot; unavailable Redis fails closed. |
| B12: project accessibility and layout | PASS for the bounded checks. Native errors link to labelled fields with descriptions/invalid state; summary focus, keyboard order, visible focus, narrow viewport/no overflow and screenshots inspected. No full accessibility audit is claimed. |
| N01: unsupported nested message rejection | FAIL, native owner. Separate `tests/NativeLimits/NestedMessageTest.php`, described below. No skipped test or expected-500 assertion hides the failure. |

The capture transport runs **after real native mail/template rendering** and
writes complete MIME bytes locally. It cannot open a network connection or
sendmail process. Preparation and every boot replace the entire mail map before
providers, so inherited SMTP URLs/failover configuration cannot escape. The
generated environment, DB target, owner and all submissions use task-only
synthetic data. This establishes local handoff, not SMTP or deliverability.

## Native limitation N01

With native `allowedMessageFields=[]`, POST
`fromEmail=visitor@contact.test` and `message[unselected]=Nested body` reaches
`SendController.php:46`, where `Summary::compile()` receives `null` and throws a
TypeError at `Submission/Summary.php:14`. The native `SubmissionRuleset` builds
its empty allowlist using Laravel `Rule::array([])`, which serializes as `array`
instead of enforcing no keys. The native validated input then has no usable
`message` for the summary. This is an inference from the unchanged owning source
and the real kernel failure; the stable observed fact is the native 500.

The independent single-request reproduction fails its expected native
redirect/field-validation assertion. It also verifies no mail and private
no-store/noindex headers before that failing assertion. The candidate's empty-list
setting must not be described as enforcing scalar-only submissions. The project
page sends scalar fields, while nested callers remain unsupported and this
defect remains unresolved. No vendor patch, fork, constraint override, upgraded
branch, custom validator or upstream issue/PR was introduced.

## Independent review correction (cycle 1)

Review of head `67e074567508ea247a73be9cc3896ca09c06d171` found that the fixture's
`cache:clear` / `Cache::flush()` calls could select an inherited Redis default
or cached external store. The generated `.env` did not override process
configuration. The reviewer stopped before providers and did not contact or
clear any external cache. The initial isolation evidence was therefore
incomplete despite the passing scalar flow.

The correction is confined to this optional fixture. Before configuration loads,
`useStoragePath()` pins the runtime's own storage root. After configuration and
before providers, the whole cache map is replaced with one file store; the
native limiter and session locks select that store explicitly. Sessions use
local files, with external connection/store choices removed. This does not
change core/production cache behavior, replace native throttling or remove
cache-reset assertions.

Six console/HTTP probes cover inherited Redis/storage choices, cached external
named stores/limiter choices, and cached external file paths. They fail against
the original bootstrap and pass after correction. An unsafe configuration exits
before a cache/store or provider resolves; only after establishing the complete
local map do the probes attempt unavailable named stores and confirm rejection.
A post-boot feature test verifies real local file cache/session handlers. The
existing real native 429 and browser session-separation tests also pass.

The red/green probes, fresh corrected install and combined results are retained
under `step07b/correction-01/`. Original verification evidence is unchanged; the
prior disposable runtime is archived there without changing its bytes. Independent
re-review and merge remain the coordinator's responsibility.

## Checks and evidence

The final run starts from another fresh empty disposable database after
archiving development attempts. Client and SSR builds, TypeScript checking of
the affected browser sources, Prettier (including Vue/styles), PHP PER-CS3 style,
PHP syntax, root/runtime Composer validation, Node syntax and whitespace checks
pass. The Vue form's actual compiled behavior is exercised through Chrome;
no duplicate Vue unit suite or new test dependency is added. Correction cycle 1
reuses the earlier TypeScript, Vue/Prettier, Node and Composer checks for unchanged
sources; PHP style/syntax, the combined PHP/browser suites and the fresh fixture
client/SSR builds run again for the corrected bootstrap.

No PHP coverage percentage is claimed: the environment has no coverage driver
and this package has no coverage threshold. This scoped result does not rerun
or relabel the earlier account regression failures, full prior suites or
production behavior. The unsupported native probe is a real separate failure;
the overall integration is not an all-capabilities green result.

Operational artifacts are under
`~/.codex/agent-team/runs/craft-inertia-execution-20261109/step07b/`:

- `verification/`: exact runtime lock, dependency/source/published manifests,
  both fixture manifests, PHP and native-limit JUnit, browser JSON/JUnit and
  screenshots, build/style/static logs and final native-state checks.
- `development-evidence/`, `development-runtime/` and development browser
  reports: initial diagnostics retained, including corrected test-only
  assumptions about Inertia version headers, old-input lifetime and XSRF cookies.
- `preservation-before.json` and completion evidence: all original 51 Playground
  hashes, native source preservation, three stashes/protected branch,
  submitted patch/hash, live PR and task-service cleanup.
- `correction-01/`: retained red/green local-storage probes, archived prior runtime,
  fresh corrected verification, updated fingerprints and correction handoff.

Only this task's HTTP process and Compose database are stopped after checking.
No original app data, environment, project config, YAML, vendor files or plugin
activation is changed. Playground work remains uncommitted. No real mail,
upstream/native change, deployment, tag, release or identity/distribution decision
is included. The coordinator owns independent review and merge; publication
does not grant STEP-07/AC-07 or production acceptance.
