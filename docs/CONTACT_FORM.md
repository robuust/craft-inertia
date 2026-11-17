# Optional native Contact Form integration

This project-owned example uses the generic
`Robuust\CraftInertia\Forms\NativeForm::props()` helper and existing Inertia
errors/flash bridge with Contact Form's native action, ruleset, system message
and mailer. Contact Form is an optional project dependency. The reusable package
registers no Contact Form listener, validation configuration or submission endpoint.

## Current source baseline (2026-11-17)

The current release is [Contact Form 4.0.0](https://github.com/craftcms/contact-form/releases/tag/4.0.0)
at `9df71d068ad2694aad2c9c9d0909c18197ae51c3`, requiring PHP
`^8.5` and Craft `^6.0.0-beta.1`. Its ruleset rejects unlisted nested message
fields with validation errors. The earlier nested-field 500 below is historical,
not a limitation attributed to 4.0.0. This update is based on source inspection
and package linting; it is **not runtime acceptance** of the new baseline.

Install and enable Contact Form separately in the consuming project:

```sh
composer require 'craftcms/contact-form:^4.0' --with-all-dependencies
php artisan craft:plugin:install contact-form --no-interaction
php artisan vendor:publish --tag=contact-form --no-interaction
```

No Contact Form-specific configuration is added to `config/craft-inertia.php`.

## Native fields and project validation

Keep recipients, attachment policy and nested fields in the native
`config/craft/contact-form.php`. For example, the Robuust form can use:

```php
return [
    'toEmail' => '$CONTACT_FORM_RECIPIENT',
    'allowAttachments' => false,
    'rateLimit' => 5,
    'allowedMessageFields' => [
        'Organisatie' => ['nullable', 'string', 'max:255'],
        'Telefoonnummer' => ['required', 'string', 'max:100'],
        'Landingpage' => ['nullable', 'string', 'max:255'],
    ],
];
```

The environment reference is resolved by native Contact Form settings. Keep the
recipient out of submitted data. Configure subject/body in the native
`contactform_submission` system message to retain the project's wording and
server-owned subject; native Reply-To continues to use the submitted email/name.

Copy [ContactFormValidation.php](examples/ContactFormValidation.php) into the
project at `app/ContactForms/ContactFormValidation.php`, adapting its business
rules as needed. Register it in the project service provider's `boot()` method
after installing and enabling Contact Form:

```php
use App\ContactForms\ContactFormValidation;
use CraftCms\Cms\Validation\Events\ValidationRulesResolving;
use Illuminate\Support\Facades\Event;

// Inside the project service provider's boot() method:
Event::listen(ValidationRulesResolving::class, ContactFormValidation::class);
```

The example scopes the native event to `SubmissionRuleset`. It appends required
name and string/length limits for name, email and body, plus the parent array
rule, using native `addRule()` / `addRules()`. It preserves native required email,
email format, nullable body, unknown-field rejection and attachment validation.

**Require the parent `message` array when a nested field is required.** Native
Contact Form accepts scalar messages and conditionally skips nested rules for
them. Without the parent array rule, a scalar could bypass required telephone.
A nonempty message array containing telephone permits a blank optional body.

These are project business rules using Craft's existing validation API. The
plugin supplies no validation registry, defaults, validator or contact data model.

## Form props and errors

Inside a project controller's private render callback, add a project-owned prop:

```php
'contactForm' => app(\Robuust\CraftInertia\Forms\NativeForm::class)
    ->props('contact-form/send', $contactSuccessUrl),
```

`$contactSuccessUrl` must be selected by the server, for example from the current
site's contact entry, not copied from request input or an unchecked referrer.
The helper returns only `action`, `csrfToken` and encrypted `redirect`.
It uses `Url::actionUrl('contact-form/send')`, `csrf_token()` and
`Crypt::encrypt()`, matching native redirect decryption. Use a fixed success
URL for pre-validation honeypot acknowledgement; redirect templates depending
on submitted values require native validated data.

Render through the existing public renderer and keep this form outside guest
SSR/public content caches using the project's `publicPagePolicy`. Resolve the
helper per request inside that callback. Never cache its token, put it in
metadata/JSON-LD, or add session state to the consumer's `PageData`. The
consumer's existing `data/layout/seo` contract can stay intact.

Submit directly to the returned action with official Inertia `Form` or
`useForm`, keeping HTML negotiation. Include hidden `_token=csrfToken` and
`redirect=redirect` inputs. Keep native action middleware and CSRF protection;
do not attach public page rendering middleware to the action.

| Dutch label | Native input name | Inertia error key |
| --- | --- | --- |
| Naam | `fromName` | `fromName` |
| E-mailadres | `fromEmail` | `fromEmail` |
| Organisatie | `message[Organisatie]` | `message.Organisatie` |
| Telefoonnummer | `message[Telefoonnummer]` | `message.Telefoonnummer` |
| Bericht | `message[body]` | `message.body` |
| Landingpage | `message[Landingpage]` | `message.Landingpage` |

For `useForm`, submit an actual nested `message` object. Preserve Dutch labels
and explicitly map the native keys above to the existing UI. Show parent
`message` errors and global failures as a form summary as well. The existing
`HandleInertiaRequests` bridge handles native session validation errors and
native error/success flash. Use the official adapter's `errorBag` option when
several forms share a page. Read native notices through `usePage().flash`,
including `success` and `error`; do not copy them into public page props.

## Preserve project anti-spam and analytics behavior

The former Robuust form acknowledges honeypots before field validation and
returns 429 when throttled. Native defaults differ, so preserve these policies
explicitly during migration:

1. Retain the native `throttle:contact-form` middleware and configure five
   attempts per minute. For the existing **429** contract, redefine its named
   limiter after native plugins boot, via
   `CraftCms\Cms\Plugin\Events\PluginsRegistered`. Use
   `Limit::perMinute(5)->by($request->getClientIp())` with a custom
   `response(Request $request, array $headers)` callback returning an explicit
   429 response with those headers. This also avoids Craft's Inertia
   `ThrottleRequestsException` redirect renderer. Native `rateLimit=5` alone
   redirects HTML/Inertia requests with error flash; JSON callers receive 429.
2. Register project middleware on the native public send route through
   `Illuminate\Routing\Events\RouteMatched`, restricted to
   `!$event->request->isCpRequest()` and
   `$event->route->getControllerClass() === SendController::class`.
   Append it with `$event->route->middleware(EarlyContactHoneypot::class)`.
   This works with cached routes and leaves native throttling before the
   appended middleware. Retain the native web/session/CSRF stack.
3. In that middleware, enforce the project's scalar honeypot input boundary.
   When `Geboortedatum` is nonempty, acknowledge without calling the next
   middleware/controller. Reuse native `RespondsWithFlash::asSuccess()` with
   the native success message and server-encrypted fixed redirect; use 303 if
   retaining the prior response status. Do not emit `MessageSent` or a true
   delivery flag. Otherwise continue to native validation/send.
   Native `MessageSending` (including the optional native honeypot plugin)
   runs **after** validation and cannot preserve this ordering.
4. Only a project listener for
   `CraftCms\ContactForm\Events\MessageSent` should set
   `Inertia::flash(['contactFormDelivered' => true])` for analytics. Scope it to
   this public form if the project has others. Client analytics should fire
   only when that flag is present. Native spam can return success without
   sending. The event proves mailer handoff, not inbox delivery.

Do not infer delivery from a successful visit, HTTP redirect or success notice.
Keep the limiter, honeypot field, analytics event names and email wording
project-owned. No SEOmatic integration or dependency is involved.

## Verification limits

This repository has no CI configuration, test scripts, coverage threshold or
automated test harness. No automated tests or coverage were run for this work,
as explicitly requested. Consuming projects still need to verify their locked
versions: empty/invalid values, scalar-parent rejection, nested errors, unknown
fields, mail failure, genuine handoff, honeypot before validation, 429/CSRF,
private headers, correct redirects, session isolation and cached-route behavior.
Issue #17's wider acceptance matrix remains open.

Pinned sources: [native validation](https://github.com/craftcms/contact-form/blob/9df71d068ad2694aad2c9c9d0909c18197ae51c3/src/Validation/SubmissionRuleset.php),
[native send/events](https://github.com/craftcms/contact-form/blob/9df71d068ad2694aad2c9c9d0909c18197ae51c3/src/Http/Controllers/SendController.php),
[native rate limiter](https://github.com/craftcms/contact-form/blob/9df71d068ad2694aad2c9c9d0909c18197ae51c3/src/Plugin.php),
[Craft flash/redirect handling](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Http/RespondsWithFlash.php)
and [Craft throttle handling](https://github.com/craftcms/cms/blob/05ce9b81be3704fb27da3a5a6670accfa49ec9a9/src/Providers/AppServiceProvider.php).

## Historical integration (2026-11-10)

Everything below records the original development baseline only and is
superseded by the source findings above for Contact Form 4.0.0. It does not
establish runtime acceptance of the current integration.

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
