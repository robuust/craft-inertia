# Optional native Contact Form integration

STEP-07B records a historical bounded REQ-13 / AC-07 integration. Its
project-owned Vue page and native action flow did not change the reusable
plugin's PHP, configuration or dependencies. The plugin no longer contains
that test fixture or its preparation scripts.
Contact Form is **not installed or required by the core package**. Full STEP-07,
account defects, general release and production acceptance remain
open. See the [evidence and native limitation](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP07B.md).

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

A consuming project installs/enables the selected owning plugin separately
and supplies its contact page, success page, controller and explicit page routes.
Register those Vue pages in the project's resolver. Historical example sources
remain in Git history; adapt them to the project's installed versions and mail
configuration instead of copying test isolation or capture transports.

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

Project privacy middleware sets headers on these selected page/action paths, including exception responses,
without changing native statuses, bodies, CSRF, throttling or session behavior.
It does not attach the public Inertia middleware to native actions. Contact pages and session/form state must stay outside guest SSR, JSON-LD and
public content caches. Compose the project's existing
[public SSR/cache eligibility](RENDERING.md) so contact pages and form/session
state remain excluded.

## Verification and remaining limits

The consuming project owns verification against its locked Craft, Contact Form
and Inertia versions. This repository has no automated test suite, disposable
consumer, mail-capture transport or preparation command. The historical matrix
above does not establish compatibility with current or other versions.

Keep recipient and delivery configuration server-owned. Verify native redirects,
validation, global failure flash, CSRF, throttling and private response policies
in the consuming project. Nested-message handling remains the documented native
limitation; no replacement validator or dependency patch is included here.

Pinned primary sources: [Contact Form action](https://github.com/craftcms/contact-form/blob/6f3b6deed57d909ab024190a53d93dedc206e13f/src/Http/Controllers/SendController.php),
[validation](https://github.com/craftcms/contact-form/blob/6f3b6deed57d909ab024190a53d93dedc206e13f/src/Validation/SubmissionRuleset.php),
[settings and system message](https://github.com/craftcms/contact-form/blob/6f3b6deed57d909ab024190a53d93dedc206e13f/src/Plugin.php),
[Craft mail rendering](https://github.com/craftcms/cms/blob/0506c32b44efbe3e7f5fded46a7c1d39de699d5b/src/SystemMessage/Mailables/SystemMessageMailable.php),
[Inertia v3 forms](https://inertiajs.com/docs/v3/the-basics/forms) and
[validation](https://inertiajs.com/docs/v3/the-basics/validation).
