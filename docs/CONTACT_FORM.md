# Optional native Contact Form integration

STEP-07B is a bounded REQ-13 / AC-07 integration, selected by the human after
STEP-07A. It supplies a project-owned Vue contact page and acceptance fixture;
the reusable plugin's PHP, configuration and dependencies are unchanged.
Contact Form is **not installed or required by the core package**. Full STEP-07,
account defects, general release and production acceptance remain
open. See the [evidence and native limitation](../reports/e2e/STEP07B.md).

## Exact capability matrix (2026-11-10, revision 2)

The only evaluated combination is official `craftcms/contact-form` **4.x-dev** at
`6f3b6deed57d909ab024190a53d93dedc206e13f`, Craft CMS
`0506c32b44efbe3e7f5fded46a7c1d39de699d5b`, CP assets
`17973259d5727a1e50a1fc85dd90566b889407af`, Laravel 13.40.0,
Inertia Laravel 3.7.0 / Vue adapter 3.11.0 and Vue 3.5.45.
Contact Form's source and dist are both locked to that exact official commit.
Its unmodified manifest requires CMS `^6.0.0`; the already accepted CMS
`6.0.0-alpha.20` development alias satisfies this constraint. This is neither a
released Contact Form 4.x nor released Craft alpha.20 compatibility claim.
The separate upstream beta-only compatibility branch was not substituted.

| Feature / boundary | Result in this exact combination | Limits |
| --- | --- | --- |
| Guest project Vue page and official Inertia `<Form>` | Exercised | Explicit project opt-in; native names `fromName`, `fromEmail`, `subject`, scalar `message`. |
| Native required email/message and field/global validation | Exercised | Redirect/session errors, native generic error flash, correction with input retained during the Inertia visit. No durable draft or reload persistence promise. |
| Optional name/subject | Exercised | Native omission and default subject; no additional frontend or server validation. |
| Native success notice and encrypted redirect | Exercised | The project generates `Crypt::encrypt()` server-side; native `RespondsWithFlash` chooses the redirect and flash. |
| Server-owned recipient and submitted reply-to | Exercised | Submitted `to`/`toEmail` cannot replace `config/craft/contact-form.php`; synthetic `.test` addresses only. |
| Native system-message rendering and mail handoff | Local capture exercised | Real `contactform_submission` template, native sender/summary and `SystemMessageMailable` reach a Symfony transport. No mail fake, real email or deliverability claim. |
| CSRF and configured per-IP throttling | Exercised | Native 419 for absent/invalid tokens without a trusted origin; native three-attempt limit and fourth-request 429. Laravel also accepts its native trusted same-origin protection. |
| Fixture cache/session storage | Exercised | Before providers: fixed application storage root, one local file cache for resets/native limiter/locks, file sessions; inherited/cached external stores and paths cannot select another backend. |
| Separate visitor sessions | Exercised | A second browser context receives neither the first visitor's input nor flash. |
| Private/no-store/noindex | Exercised | Page HTML/JSON, action redirects, CSRF/throttle errors. No contact state goes to SSR, JSON-LD or public content-data caching. |
| Attachments disabled | Exercised | Native `allowAttachments=false` rejects an uploaded file without mail. Enabling attachments, limits and delivery are unverified. |
| Nested `message` fields | **Not supported; native regression fails** | With native `allowedMessageFields=[]`, an unselected nested key produces a 500 in `Summary::compile(null)`, instead of validation. The native empty-list setting must not be presented as an enforced scalar-only policy. |
| JSON callers, custom events, custom mail templates, native CP/settings UI | Unverified | Default success events occur naturally; custom listeners/settings UI and alternate response contracts need separate acceptance. |
| SMTP, external mail services, production, other versions/plugins | Unverified | No generalized third-party compatibility or deployment claim. |

## Project integration

The optional [fixture overlay](../tests/e2e/contact-form) reuses the neutral
[STEP-07A application](../tests/e2e/installation). A consuming project needs to
install/enable the selected owning plugin separately. Copy/adapt the project
[controller](../tests/e2e/contact-form/app/Http/Controllers/ContactController.php),
[Vue page](../tests/e2e/contact-form/resources/js/pages/Contact.vue),
[success page](../tests/e2e/contact-form/resources/js/pages/ContactThanks.vue) and
the explicit project page routes. Register the pages in the project's resolver.
Do not copy the fixture's capture transport or task isolation into production.

`/contact` is a selected public Inertia page. Its controller only chooses the
presentation, emits the native action URL, encrypts the project success URL and
projects four whitelisted scalar old-input values. It does not submit or
validate. `<Form>` owns data submission, errors and processing; the page reads
native flash reactively through `usePage().flash`. Labels, required indicators,
field descriptions, an error-summary focus target, disabled processing button
and visible keyboard focus provide the bounded accessible example.

POST goes directly to `/actions/contact-form/send`, outside the public Inertia
middleware. Native `SubmissionRuleset`, `Settings`, `throttle:contact-form`,
`RespondsWithFlash`, sender/summary compilers, events and the Craft system-message
mailer remain the owners. Keep HTML negotiation for this Inertia flow; do not
wrap it in a generic JSON adapter or copy the action/controller. The default
error bag is mapped to the form's `contact` bag by the official Laravel adapter.
Native failure flash is also displayed because field errors alone do not cover
every native failure.

Set the recipient, prefixes, notice and rate limit through the owning plugin's
`config/craft/contact-form.php`. The fixture uses three attempts/minute per IP
for proof, not as a recommended production policy. It leaves attachments off.
The Vue page submits only scalar basic fields. Unsupported nested callers are
an explicit native limitation; no replacement validator, fork, vendor fix or
constraint bypass is included.

The project [privacy middleware](../tests/e2e/contact-form/app/Http/Middleware/ContactPrivacy.php)
sets headers on these selected page/action paths, including exception responses,
without changing native statuses, bodies, CSRF, throttling or session behavior.
It does not attach the public Inertia middleware to native actions. This private
fixture disables SSR with the official `Inertia::disableSsr(true)` API; it adds
no JSON-LD or content cache. Larger projects should compose their existing
[public SSR/cache eligibility](RENDERING.md) so contact pages and form/session
state remain excluded.

## Repeat the isolated proof

Use the [installation prerequisites](INSTALLATION.md#repeat-the-isolated-proof).
The optional profile has its own runtime, Compose project, database and loopback
HTTP port. Default STEP-07A and other suites neither install nor load Contact
Form. Only dependency metadata is read from the original Playground; its code,
DB, config, environment and vendor tree stay untouched.

```sh
export POC_INSTALL_PROFILE=contact-form
export POC_INSTALL_COMPOSE_PROJECT=craft-inertia-contact-form
export POC_INSTALL_REPORT_DIR=/absolute/task-owned/contact-form-evidence
node tests/e2e/install-prepare.mjs
cd tests/e2e/.runtime/contact-form
php vendor/bin/phpunit --log-junit "$POC_INSTALL_REPORT_DIR/phpunit.xml"
# This separate negative probe currently FAILS with the documented native 500:
php vendor/bin/phpunit tests/NativeLimits/NestedMessageTest.php \
  --log-junit "$POC_INSTALL_REPORT_DIR/native-limits.xml"
cd public
php -d opcache.enable=0 -S 127.0.0.1:18778 \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
# From the plugin checkout in another shell:
npx playwright test --config=tests/e2e/contact-form.config.ts
```

The existing generated database-target guard runs before providers on CLI,
HTTP and test boots. The optional bootstrap fixes the application's storage root
to this disposable runtime before configuration loads. After configuration, but
before any provider, it replaces the entire cache map with one local file store
and explicitly selects it for native throttling and session locks. File sessions
use the same runtime's own storage directory; inherited Redis/session choices,
cached external file paths and alternate named stores cannot redirect these
operations. Tests may therefore reset only this fixture's local cache. Six
console/HTTP probes stop before providers even when reproducing the previous
unsafe configuration; the real native limiter is still exercised separately.

The optional bootstrap also replaces the entire
mail configuration with one local capture transport and forces synchronous
handoff before providers. Inherited and cached SMTP URLs/config cannot select
an external transport; a native project setting selecting an unknown mailer
fails closed. Native rendering still runs before capture. Only generated
synthetic `.test` submissions are used; captured MIME files are local under
`storage/mail-capture`, with no network or sendmail process.

Preparation checks the exact source/dist pins, unchanged remaining Composer
package records, original frontend lock, native source manifests and published
CP asset bytes. It records both fixture manifests and the resolved runtime lock.
The native limitation probe is separate from the supported scalar suite and is
reported as a real failure, never skipped or counted as passing.

Stop the task-owned PHP process and database after verification:

```sh
docker compose -f "$POC_INSTALL_REPORT_DIR/compose.yaml" \
  -p "${POC_INSTALL_COMPOSE_PROJECT:-craft-inertia-contact-form}" down
```

Retain evidence before archiving only this disposable runtime and preparing a
fresh one. No real email, original app activation, upstream publication, Craft
upgrade, package release or deployment belongs to this slice.

Pinned primary sources: [Contact Form action](https://github.com/craftcms/contact-form/blob/6f3b6deed57d909ab024190a53d93dedc206e13f/src/Http/Controllers/SendController.php),
[validation](https://github.com/craftcms/contact-form/blob/6f3b6deed57d909ab024190a53d93dedc206e13f/src/Validation/SubmissionRuleset.php),
[settings and system message](https://github.com/craftcms/contact-form/blob/6f3b6deed57d909ab024190a53d93dedc206e13f/src/Plugin.php),
[Craft mail rendering](https://github.com/craftcms/cms/blob/0506c32b44efbe3e7f5fded46a7c1d39de699d5b/src/SystemMessage/Mailables/SystemMessageMailable.php),
[Inertia v3 forms](https://inertiajs.com/docs/v3/the-basics/forms) and
[validation](https://inertiajs.com/docs/v3/the-basics/validation).
