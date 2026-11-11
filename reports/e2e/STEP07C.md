# STEP-07C permanent private package identity

This bounded AC-07 identity/installation-migration slice adopts Composer
`robuust/craft-inertia`, PHP `Robuust\CraftInertia`, native Craft handle `inertia`
and display name `Inertia`. The human selected private distribution and retaining
the proprietary license. The private npm harness is `@robuust/craft-inertia`.
Schema version `1.0.0`, all dependency constraints and native pins are unchanged.
There is no public release, tag, npm/Packagist publication, visibility change,
framework/plugin fix or accepted account implementation.

Base: merged STEP-07B commit `935df5dc922d391cecd572cf59149c7864614b38`.
The [current installation and migration checklist](../../docs/INSTALLATION.md)
contains the operational sequence; [FRONTEND.md](../../docs/FRONTEND.md) remains
the canonical roadmap. Publication, independent review and merge are separate
from the results below.

## Current owner boundaries

The integration publishes `config/craft-inertia.php` through tag
`craft-inertia-config`; its `craft-inertia.rootView` and
`craft-inertia.indexingPolicy` retain their original meanings. The official
Inertia Laravel adapter alone owns `config/inertia.php` / `inertia.*`, including
SSR. Native Craft continues to discover/register the plugin from Composer
metadata. No duplicate provider, handle registry, transport or compatibility
alias exists. No public route, response/flash/error/privacy/indexing policy or
SSR gateway changed to perform the rename.

Current source, minimal/contact/content fixtures, path repositories, imports,
native lifecycle commands and config references use the canonical identity.
The original source Composer lock remains a frozen input containing the legacy
package. Disposable consumer manifests/locks replace only that package record;
all other package records are compared in full. The root npm lock changes only
its two package-name fields. Historical reports, the released-alpha diagnostic,
old demo URL negatives, synthetic email domains and immutable source/stash facts
retain their actual historical identity.

## Disposable legacy-to-canonical proof

`tests/e2e/identity-migrate.mjs` archives the immutable predecessor with
`git archive`, then uses its legacy installer to create a real installed
`inertia-poc` consumer on the fixed native pins. Only its task bootstrap
storage/mail isolation and Compose-project selection are patched; archived
package PHP/config/Composer metadata remain unchanged.

The installed `Plugins.php`, install/uninstall/enable/disable commands and
`Installable` trait were inspected first. There is no native handle-rename API.
The core has no install migration/content table. Native uninstall removes its
plugin/project-config registration and migration track; no hand-written plugin
DB/YAML mutation or forced uninstall is used.

Nine independent application boots exercise legacy enabled/disabled/uninstalled,
canonical uninstalled/enabled/disabled/re-enabled, project opt-out and restored
opt-in. Old route/config caches are cleared while old classes still exist. A
known disabled legacy installation is enabled with project fallback selected
before normal native uninstall. Composer replaces the package in one partial
update; project imports, tests and config filename are migrated before discovery.
Native install and non-forced publication preserve the project's custom file.
The new lifecycle also runs with cached routes/config.

Each checkpoint compares exact native users, elements, sites/site groups,
entries, fields and sections, a nonempty project-owned table and unrelated
project-config branches. The custom namespaced root view and indexing callable
survive. Native fallback, direct HTML, Inertia JSON, stale-version 409, unknown
404, native action/API/feed handling and route-specific indexing are checked.
The official custom SSR config file stays byte-identical. Native plugin IDs and
installation bookkeeping may change by design; application data does not.

Result: **9 tests / 303 assertions, no failures/errors/skips**. This proves the
known installed legacy source path on the exact predecessor, including disabled
preparation; it does not prove missing-vendor recovery, forced uninstall, custom
uninstall hooks, an original production DB upgrade or all historical versions.

## Scoped regression results

| Check                                       | Result                         | Boundary                                                                                                                         |
| ------------------------------------------- | ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| Canonical neutral consumer PHP              | 28 tests / 130 assertions pass | Native discovery/name/schema, published config owner, HTML/JSON/status, opt-out/lifecycle and pre-provider DB/storage isolation. |
| Neutral browser                             | 5 pass                         | Install alone, JS-free SSR, Inertia navigation, cached-route disable/enable, project opt-out.                                    |
| Optional Contact Form PHP                   | 29 tests / 123 assertions pass | Same scalar contract and accepted DB/cache/session/mail isolation, canonical middleware import.                                  |
| Contact Form browser                        | 5 pass                         | Scalar validation/success/capture, session separation, CSRF/throttle and narrow keyboard flow.                                   |
| Public/content/rendering/indexing/cache PHP | 91 tests / 513 assertions pass | Canonical core with mechanically migrated local consumer, before adding the local partial STEP-05 adapter.                       |
| Public predecessor browser                  | 98 pass; no skips/retries      | See the local-only boundary below.                                                                                               |
| Native account token regressions            | 2 ordinary failures            | Completed activation retains a usable token; token reuse changes the password.                                                   |
| Native required-enrollment regressions      | 2 ordinary failures            | Native setup/verification actions intercepted by the native required-2FA barrier.                                                |
| Contact Form N01                            | 1 ordinary failure             | Unselected nested message still reaches `Summary::compile(null)` / 500.                                                          |

The four account failures and N01 remain separate real failures. No skips,
expected-failure conversions, native patches, weakened rate limits or acceptance
waivers were introduced. Full STEP-05, full AC-06, full AC-07, general upgrades,
production deployment and release readiness remain incomplete.

The original Playground contains unaccepted partial STEP-05 source. Its old
public browser expectations conflict with that consumer without the preserved
adapter: an initial run passed 40, failed two and left 56 dependent cases unrun.
A second local combined run passed 41 and found one inherited expectation of
an email in public user props, leaving the same dependent cases unrun. The
agreed local user projection is only `id`/`username`. The final **local-only**
combined source restores the preserved account adapter mechanically under the
new namespace and uses its private-auth SSR expectation; it also asserts that
exact minimal projection and absence of email. Neither account code nor those
account-aware test changes enter this core PR. This contextual regression
exercise cannot establish core-only account support.

Composer strict validation, TypeScript, Prettier, whitespace and PHP syntax
checks pass. PHP style follows the existing core conventions; the copied
storage regression retains the accepted Contact Form PER-CS3 formatting.
Client/SSR builds pass for neutral, contact and local public consumers. Vue
source and dependency versions are unchanged; no new Vue unit coverage or PHP
coverage percentage is claimed.

## Evidence and preservation

Operational evidence is local under
`~/.codex/agent-team/runs/craft-inertia-execution-20261109/step07c/`:

- `clean02/`: install/dependency/native-asset manifests, final PHP and five browser cases.
- `contact/`: exact optional pin/other-package comparisons, PHP/browser and separate N01.
- `migration-final/`: archived source, legacy installation, nine checkpoint JSON/XML files,
  exact data/config comparisons, old/new locks and `migration.json`.
- `public/`: 91-test PHP result, four separate native account failures and retained
  initial/interrupted/context-mismatch runs; `partial05-isolated.json` records the local boundary.
- `public-recovery-final/` and `public-recovery-source/`: final fresh local-only combined public regression; the earlier `public-final/` run retains its DB/network timeouts.
- `local-source-migration/`: corrected exact incremental 11-file Playground patch/copies in `corrected/` and
  separate seven-file partial STEP-05 namespace/config patch/copies, with before/after
  hashes. The corrected Playground patch was applied to a task-owned copy and every after hash matched. The earlier 14-file patch is superseded: three unrelated synthetic-email-only replacements were removed.
- Final source/lock/criterion/preservation/cleanup/publication records identify the
  delivered source and remaining boundaries.

Earlier setup/check failures remain preserved: the empty-database guard refused
reuse of an older stopped fixture DB; isolated Compose projects resolved that
without deleting predecessor data. The configuration-owner assertion caught an
initial merge under `inertia`, corrected to `craft-inertia` before final proof.
A migration command-construction error failed before installation. Early public
PHP invocation/configuration misses were corrected by supplying the fixture file,
using the supported PHPUnit filter, and retaining a safe native in-memory array
cache alongside the fixed local file store. An overlapping public browser launch
was interrupted and its output retained. A later full browser run reached 62 passes before database connection timeouts
and PHP process termination; its 18 failures/18 unrun cases remain preserved.
A fresh task-owned recovery runtime uses an explicit Node 24 runner. The current
source migration narrows config-key replacement to quoted config identifiers,
leaving synthetic email domains intact. Earlier browser copies with changed
synthetic test domains remain historical evidence only; the affected test files
are not part of the public browser application flow. None of these failed runs
is counted as a passing acceptance run.

The original Playground stays read-only on its unchanged branch/commit with all
51 local-file hashes retained. Its original DB/YAML/env/vendor are not activated.
Protected branch `feature/native-accounts-access` stays at
`e9d1ae3019c09f82d192cdc009084bf201074b40`; stashes
`ffa7e630f39a0544d1bd437e33788f0d42d05b04`,
`20dacafb9ae1729d1ae2fe7b5f6bfefbd9493f17` and
`a13bfe765f78af9fce896dc9032c92082f7160b6` remain intact. Disposable and original
Playground work is uncommitted and outside the plugin PR. Task-owned services
are stopped after verification; useful runtime/database evidence is retained.
