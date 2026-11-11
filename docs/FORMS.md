# Forms and private files

Native `inertia:` entry templates provide read-only GET/HEAD rendering and
selected public content. They add no action, upload, download or account system.
The existing mutation and private-file contracts below remain project/native
responsibilities; adding a field to a page does not authorize an action.


STEP-04 uses the official Inertia Laravel 3.7 / Vue 3.11 APIs with the approved
CMS `0506c32b44efbe3e7f5fded46a7c1d39de699d5b` and matching assets
`17973259d5727a1e50a1fc85dd90566b889407af`. These are exact official development
sources, aliased for Composer compatibility; they are not the released alpha.20.
See [STEP-04 evidence](https://github.com/robuust/craft-inertia/blob/6ee7ac878370a854da076b41087687a8642860f3/reports/e2e/STEP04.md).

## Select the transport for each operation

| Owner / operation | Request and response | Project caller |
| --- | --- | --- |
| Native `entries/save-entry` | Native `entryId`, `siteId`, `title`, encrypted `redirect`; HTML-accepting request redirects, flashes old input and field errors on failure. | Official `<Form>`, named `nativeEntry` error bag; numeric IDs in its transform. |
| Project `files/search` | Laravel `validateWithBag('articleSearch')`; native notice; redirect to current-site overview with `q`. | A separate `<Form>`, with `articleSearch` errors. |
| Project `files/upload` | Multipart `file`, native `folderId` / `siteId`; delegates native `AssetUploads::authorize()` and `complete()`. Returns the native JSON status and payload unchanged. | Official `useHttp`; it owns file data, errors, progress and processing. |
| Native `assets/uploads` and upload sessions | JSON metadata `filename`, `size`, target IDs → 201 session with transport/URLs. Native TUS transfer, status, complete and cancel have their own contract. | Native clients or an explicit integration for that transport. The project multipart example does not reimplement TUS. |
| Project `files/{assetId}/download` | Authenticated, configured-volume ID lookup; native `viewFile` authorization and `Assets\ActionController::downloadAsset()`. Streams attachment with native MIME/length. | An ordinary document link, outside Inertia page middleware. |

The native entry controller currently calls strictly typed service methods with
request IDs unchanged. Named HTML fields are strings; the example's `<Form>`
transform converts the two IDs to numbers before the official JSON submission.
No field renaming or vendor patch is involved. This does not demonstrate a plain
URL-encoded submission to every native controller.

Craft `RespondsWithFlash` chooses JSON when `expectsJson()` is true: success is
200 with optional `message` and `redirect`, failure is 400 with optional
`message`, `errorCode` and `errors`. Otherwise failure flashes old input, optional
nonempty default field errors and an error message; success redirects. Posted
`redirect` must be generated with `Crypt::encrypt()` server-side. The project
never constructs redirect ciphertext or trusts a visitor-supplied path.

## Session errors and flash

Apply `HandleInertiaRequests` to selected project page/form routes, following
[the public boundary](../README.md#bespoke-project-routes). Native action routes
keep their existing middleware. The plugin does not intercept or convert action
JSON. Normal Laravel validation and the official adapter own error bags.

On an opted-in page, the plugin copies native `notice`, `success` and `error`
session messages into official Inertia **flash data**, under those same keys.
Existing host flash values win on a key collision; intentional shared props are
retained. Read `usePage().flash` reactively in a project layout. Flash data is
outside browser history; render messages as text.

A native session failure may have no field errors. If an `error` message exists
and the submitting form's requested bag has no errors, the middleware supplies
`errors.<bag>.message` (or `errors.message` without a requested bag). This makes
Inertia's normal error callback run instead of a false success. Existing field
errors and other named bags remain intact. The project displays this non-field
`message` alongside its field errors. It does not copy the full session old
input into props: the controller reads only intended `old('title')` / `old('q')`
values. Inertia keeps edited fields during failed submissions.

## Explicit native JSON failures

`useHttp` is appropriate for the JSON upload operation; it must not be sent as
an Inertia page visit. Inertia Vue 3.11 handles standard 422 field errors itself;
its `onError` receives only the error map. Native message-only 422 exceptions
therefore get a project fallback explaining file type/size rejection. The
project's `onHttpException` reads native `errors` / `message` for other statuses,
including expired CSRF sessions, and handles network failure. These use the
same form owner's error API, without a parallel store or validator.

Native upload completion can return **200 with `conflict`**. The caller treats
that as an error even if `assetId` exists, keeps the file for correction and
never displays success or reloads the file list. An accepted result requires
`assetId` and no conflict; only then are the input and form file reset and
permitted file metadata reloaded. A stale `response` alone is never success.
This example reports conflicts; overwrite/rename resolution is not implemented.

## Private storage and authorization

The project requires an explicitly configured existing private volume in
`config/site-files.php`; the default `null` creates no volume or domain policy.
The example refuses a volume with public URLs or public disk visibility. The
isolated fixtures alone create a synthetic private local disk/volume and users.
Do not point the example at a publicly served uploads directory.

Before storage, the project fixes the target to that volume's root folder and
current native site. Craft's `AssetUploads` checks its configured extension/size
limits and target permission. `AssetUploadHandler` owns MIME inspection,
validation, sanitization, conflicts, element creation and storage. The temporary
source path comes from PHP's uploaded file, never a request path. Project and
native validation stay in their owning layers.

Protected file routes retain both `auth:craft` and native
`AuthenticateCraftSession`, so a session invalidated by a password change cannot
continue listing, uploading or downloading. The multipart upload keeps the native
`throttle:60,1` initiation limit. These are existing native protections, not new
account or rate-limit policies.

Lists include only assets allowed by native `viewFile`; downloads query the
configured volume across native sites and authorize before streaming. No disk
path or public asset URL is projected. Byte responses stay `private, no-store`.
The native CP download action's controller is reused deliberately: its default
route also requires CP access, which a public project must not remove globally.
Guests and users lacking file permissions cannot obtain file bytes or metadata.

CSRF remains enabled for native and project actions. The examples retain the
native Craft guard and session; complete account challenges belong to STEP-05.
The separately selected [Contact Form integration](CONTACT_FORM.md) adds a bounded
third-party scalar-form matrix and native limitation. This STEP-04 proof makes no
claim about arbitrary native actions, other third-party forms, remote upload
transports, production storage or existing database upgrades. Official API
references: [Inertia forms](https://inertiajs.com/docs/v3/the-basics/forms),
[standalone HTTP requests](https://inertiajs.com/docs/v3/the-basics/http-requests)
and the installed Craft `Http/RespondsWithFlash.php`, `Support/Flash.php`,
`Asset/AssetUploads.php`, `Asset/AssetUploadHandler.php`, `Filesystem/Uploads.php`
and native policies at the exact source above.
