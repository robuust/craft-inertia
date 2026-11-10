# STEP-07A — Clean installation and native fallback

## Delivered boundary

This independent AC-07 subset installs the current private package in a genuinely
empty Craft application on the approved development pins. Only dependency
manifests and lockfiles are reused from the original Playground. No application
code/config, database, project YAML, sections, custom fields or demo entries are
copied. Native installation creates its isolated owner/site and project config.
The fresh database has zero sections, fields and entries after installation.

The neutral project owns `/` and `/about`, a small controller, explicit public
string/link props, the namespaced public Blade root, Vue page/resolvers, Vite
entries and both client/SSR builds. The package's `src/`, configuration, Composer
metadata, identities and dependencies are unchanged from predecessor
`e9d1ae3019c09f82d192cdc009084bf201074b40`. Craft owns provider registration;
the package publishes configuration only and takes over no public URL implicitly.

Project integration defaults off. Its single predicate combines the project
setting with native `Plugins::isPluginEnabled('inertia-poc')`. Both the project's
middleware and controller honor it. Native `craft:plugin:disable` and
`craft:plugin:enable` persist state through Craft's APIs; fresh boots prove the
provider disappears/returns. Native fallback pages keep status 200 and their
expected content, without a public bundle or Inertia envelope, including with
Inertia request headers. Per-request selection also works with cached routes.
An enabled plugin can independently be opted out by project configuration.

Unknown public URLs retain native 404s. Native CP login, session-info actions,
the project API and XML feed retain native status/content. The project confines
SSR to its static public pages with the official adapter API; CP pages never
enter its public resolver. An open Inertia client needs a full-page visit after
the project switches to native HTML. This fixture is not an account, complete
CP, content-schema or production privacy acceptance suite.

## Provenance and checks

The [repeatable checklist](../../docs/INSTALLATION.md) records setup, opt-in,
fallback, lifecycle commands, development, builds and SSR. The independent
[`install-prepare.mjs`](../../tests/e2e/install-prepare.mjs) refuses an existing
runtime or nonempty task database. It reuses the exact approved root lock,
updates only the local plugin path metadata and fails on any other dependency
change. The original demo-specific harness and earlier suites remain unchanged.
The separate installation Playwright config excludes generated runtimes only.

- CMS `0506c32b44efbe3e7f5fded46a7c1d39de699d5b`, matching cms-assets
  `17973259d5727a1e50a1fc85dd90566b889407af`. The development alias is not
  released alpha.20 support.
- PHP 8.5.12, Node 24.15.0, Yarn 4.14.1, Laravel 13.40.0, Inertia Laravel 3.7.0,
  Inertia Vue 3.11.0, Vue 3.5.45 and Vite 8.3.6. The isolated PostgreSQL image is
  pinned by digest. No floating dependency baseline was introduced.
- All 2,821 CMS, 8,097 cms-assets and 8,093 published native asset files match
  the accepted STEP-06C byte manifests. Installed plugin runtime/config files
  match this checkout. Source and dist identities retain the exact pins.

| Check | Final result and limits |
| --- | --- |
| Fresh Composer/Craft/plugin install | Passed; empty task DB, normal discovery and native commands |
| PHP installation/lifecycle/response contracts | 8 tests, 82 assertions passed |
| Browser HTML/SSR/navigation/native lifecycle/project opt-out | 5 passed, zero failures/skips/retries |
| Project client and SSR builds | Passed from the locked dependency graph |
| TypeScript, Prettier, PHP syntax, scoped PHP CS Fixer | Passed |
| Composer metadata and Git whitespace | Passed |
| Native source/asset and original-work preservation | Exact hashes retained and checked |
| Independent review and merge | Pending coordinator review; PR deliberately remains open |

PHP coverage is not claimed: no coverage driver is installed, and this package
has no PHP coverage gate or CI workflow. No threshold or existing suite was
weakened. The accepted STEP-06C combined PHP119/Vue67/browser36 evidence is
reused for untouched behavior; it is not a new aggregate run. Its four explicit
native account regression failures remain open and were not rerun or relabelled.

This pinned native revision can raise a duplicate `APP_NAME` constant when
`config:cache` is invoked directly over an existing cached configuration. The
documented, verified sequence is separate native `config:clear` and
`config:cache` commands. No native fix is included. The local HTTP harness turns
off bytecode caching for rapid config replacement; production workers require
their normal deployment restart process. The SSR process uses supported Node
explicitly and binds to loopback. Development attempts and their diagnostics are
retained separately from the final clean run.

## Local evidence and remaining work

Operational evidence is retained under
`~/.codex/agent-team/runs/craft-inertia-execution-20261109/step07a/`:
the `verification` directory holds install/build logs, dependency/source and
published-asset manifests, exact resolved lock, PHP JUnit and browser JSON/JUnit,
screenshots and reports. The completion handoff records the stable head/PR,
patch/hash, original source/stash preservation and task-service cleanup.
No new agent or extra task chat was created.

Original Playground source, DB/YAML/env/vendor and the preserved partial
STEP-05 plugin work/stashes stay intact and unpublished. Only plugin docs and
the isolated acceptance harness are committed. No original Playground change,
real mail, native dependency patch, production deployment, repository-setting
change, tag or release is included.

Full STEP-07/AC-07 remain incomplete. Package name/namespace/handle migration,
license/distribution choices, upgrades, selected third-party integrations,
native account defects, public HTTP output caching and general release need
their own decisions and acceptance. The coordinator owns independent review
and merge before assigning any next roadmap slice.
