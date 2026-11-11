import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import {
  copyFileSync,
  existsSync,
  mkdirSync,
  readFileSync,
  renameSync,
  writeFileSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const evidence = process.env.POC_IDENTITY_REPORT_DIR;
if (!evidence || !path.isAbsolute(evidence) || existsSync(evidence))
  throw new Error(
    "Set POC_IDENTITY_REPORT_DIR to a new absolute task-owned directory.",
  );
// Immutable merged predecessor, including the accepted pre-provider database guard.
const composeProject = `craft-inertia-identity-${createHash("sha256").update(evidence).digest("hex").slice(0, 8)}`;
const predecessor = "935df5dc922d391cecd572cf59149c7864614b38";
const legacy = path.join(evidence, "legacy-source");
const application = path.join(legacy, "tests/e2e/.runtime/installation");
mkdirSync(path.join(evidence, "logs"), { recursive: true });
mkdirSync(legacy);
function run(name, command, args, cwd = application, environment = {}) {
  console.log(name);
  try {
    const output = execFileSync(command, args, {
      cwd,
      encoding: "utf8",
      env: { ...process.env, ...environment },
      stdio: "pipe",
    });
    writeFileSync(path.join(evidence, "logs", `${name}.log`), output);
    return output.trim();
  } catch (error) {
    writeFileSync(
      path.join(evidence, "logs", `${name}.log`),
      `${error.stdout ?? ""}\n${error.stderr ?? ""}`,
    );
    throw new Error(`${name} failed; inspect the task log.`, { cause: error });
  }
}
run(
  "archive-predecessor",
  "git",
  [
    "archive",
    "--format=tar",
    "-o",
    path.join(evidence, "legacy.tar"),
    predecessor,
  ],
  root,
);
run(
  "extract-predecessor",
  "tar",
  ["-xf", path.join(evidence, "legacy.tar"), "-C", legacy],
  root,
);
const legacyManifest = JSON.parse(
  readFileSync(path.join(legacy, "composer.json")),
);
if (
  legacyManifest.name !== "robuust/craft-inertia-poc" ||
  legacyManifest.extra.handle !== "inertia-poc"
)
  throw new Error("Unexpected legacy identity.");
// Apply only current fixture storage/mail isolation; legacy package source stays immutable.
copyFileSync(
  path.join(root, "tests/e2e/installation/bootstrap/app.php"),
  path.join(legacy, "tests/e2e/installation/bootstrap/app.php"),
);
const legacyInstaller = path.join(legacy, "tests/e2e/install-prepare.mjs");
writeFileSync(
  legacyInstaller,
  readFileSync(legacyInstaller, "utf8").replace(
    "`craft-inertia-${profile}`",
    JSON.stringify(composeProject),
  ),
);
run(
  "prepare-legacy",
  process.execPath,
  [path.join(legacy, "tests/e2e/install-prepare.mjs")],
  root,
  {
    POC_INSTALL_PROFILE: "",
    POC_INSTALL_REPORT_DIR: path.join(evidence, "legacy-install"),
    POC_PLAYGROUND_PATH:
      process.env.POC_PLAYGROUND_PATH ??
      path.resolve(root, "../craft6-playground"),
  },
);
const artisan = (name, args, env = {}) =>
  run(name, "php", ["artisan", ...args, "--no-interaction"], application, env);
copyFileSync(
  path.join(root, "tests/e2e/identity/IdentityTest.php"),
  path.join(application, "tests/Feature/IdentityTest.php"),
);
writeFileSync(
  path.join(application, "app/UpgradePolicy.php"),
  `<?php
namespace App;
class UpgradePolicy {
    public static function indexable(\\Illuminate\\Http\\Request $request): bool { return $request->routeIs('about'); }
}
`,
);
const customConfig = `<?php\nreturn ['rootView' => 'frontend::upgrade', 'indexingPolicy' => [App\\UpgradePolicy::class, 'indexable']];\n`;
writeFileSync(path.join(application, "config/inertia-poc.php"), customConfig);
writeFileSync(
  path.join(application, "config/inertia.php"),
  `<?php\nreturn ['ssr' => ['enabled' => false, 'url' => 'http://127.0.0.1:13727/custom-upgrade']];\n`,
);
writeFileSync(
  path.join(application, "resources/views/upgrade.blade.php"),
  readFileSync(
    path.join(application, "resources/views/app.blade.php"),
    "utf8",
  ).replace("<body>", '<body data-identity="identity-custom-root">'),
);
const digest = (file) =>
  createHash("sha256")
    .update(readFileSync(path.join(application, file)))
    .digest("hex");
const officialConfigHash = digest("config/inertia.php");
const legacyLock = JSON.parse(
  readFileSync(path.join(application, "composer.lock")),
);
function probe(stage) {
  run(
    stage,
    "php",
    [
      "vendor/bin/phpunit",
      "tests/Feature/IdentityTest.php",
      "--log-junit",
      path.join(evidence, `${stage}.xml`),
    ],
    application,
    { IDENTITY_STAGE: stage, IDENTITY_REPORT_DIR: evidence },
  );
  if (digest("config/inertia.php") !== officialConfigHash)
    throw new Error("Official adapter configuration changed.");
}
function configCache(name, enabled = true) {
  artisan(`${name}-clear`, ["config:clear"]);
  artisan(`${name}-cache`, ["config:cache"], {
    FRONTEND_ENABLED: String(enabled),
  });
}
configCache("legacy");
artisan("legacy-route-cache", ["route:cache"]);
probe("legacy-enabled");
artisan("legacy-disable", ["craft:plugin:disable", "inertia-poc"]);
probe("legacy-disabled");
// Native uninstall forbids a disabled plugin without force. Restore its known old code first.
artisan("legacy-enable", ["craft:plugin:enable", "inertia-poc"]);
// Clear caches with old classes still available; keep the explicit project fallback during replacement.
artisan("legacy-route-clear", ["route:clear"]);
configCache("fallback", false);
artisan("legacy-uninstall", ["craft:plugin:uninstall", "inertia-poc"]);
probe("legacy-uninstalled");
artisan("legacy-config-clear", ["config:clear"]);
const composerPath = path.join(application, "composer.json");
const composer = JSON.parse(readFileSync(composerPath));
delete composer.require["robuust/craft-inertia-poc"];
composer.require["robuust/craft-inertia"] = "0.1.0";
composer.repositories[0] = {
  type: "path",
  url: root,
  options: { symlink: true, versions: { "robuust/craft-inertia": "0.1.0" } },
};
writeFileSync(composerPath, JSON.stringify(composer, null, 4));
// One partial Composer update removes the legacy package and installs its new identity.
run("composer-replace", "composer", [
  "update",
  "robuust/craft-inertia",
  "robuust/craft-inertia-poc",
  "--no-interaction",
  "--no-scripts",
  "--prefer-dist",
]);
const lock = JSON.parse(readFileSync(path.join(application, "composer.lock")));
const otherPackages = (input) =>
  [...input.packages, ...input["packages-dev"]].filter(
    ({ name }) =>
      !["robuust/craft-inertia", "robuust/craft-inertia-poc"].includes(name),
  );
if (
  JSON.stringify(otherPackages(lock)) !==
  JSON.stringify(otherPackages(legacyLock))
)
  throw new Error("Other package records changed.");
for (const relative of [
  "app/Http/Middleware/PublicFrontend.php",
  "tests/Feature/InstallationTest.php",
]) {
  const file = path.join(application, relative);
  writeFileSync(
    file,
    readFileSync(file, "utf8")
      .replaceAll("CraftInertiaPoc", "CraftInertia")
      .replaceAll("robuust/craft-inertia-poc", "robuust/craft-inertia")
      .replaceAll("config/inertia-poc.php", "config/craft-inertia.php")
      .replaceAll(
        "config_path('inertia-poc.php')",
        "config_path('craft-inertia.php')",
      )
      .replaceAll("'inertia-poc'", "'inertia'"),
  );
}
renameSync(
  path.join(application, "config/inertia-poc.php"),
  path.join(application, "config/craft-inertia.php"),
);
if (
  readFileSync(path.join(application, "config/craft-inertia.php"), "utf8") !==
  customConfig
)
  throw new Error("Project integration config changed.");
artisan("canonical-discover", ["package:discover"]);
configCache("canonical-fallback", false);
probe("canonical-uninstalled");
artisan("canonical-install", ["craft:plugin:install", "inertia"]);
artisan("canonical-publish", ["vendor:publish", "--tag=craft-inertia-config"]);
if (
  readFileSync(path.join(application, "config/craft-inertia.php"), "utf8") !==
  customConfig
)
  throw new Error("Publish overwrote custom integration config.");
configCache("canonical");
artisan("canonical-route-cache", ["route:cache"]);
probe("canonical-enabled");
artisan("canonical-disable", ["craft:plugin:disable", "inertia"]);
probe("canonical-disabled");
artisan("canonical-enable", ["craft:plugin:enable", "inertia"]);
probe("canonical-reenabled");
configCache("canonical-optout", false);
probe("canonical-optout");
configCache("canonical-restore");
probe("canonical-restored");
copyFileSync(
  path.join(application, "composer.lock"),
  path.join(evidence, "canonical-composer.lock"),
);
copyFileSync(
  path.join(application, "composer.json"),
  path.join(evidence, "canonical-composer.json"),
);
writeFileSync(
  path.join(evidence, "migration.json"),
  JSON.stringify(
    {
      predecessor,
      composeProject,
      application,
      legacyManifest,
      canonicalManifest: JSON.parse(
        readFileSync(path.join(root, "composer.json")),
      ),
      otherComposerPackageRecordsUnchanged: true,
      officialConfigHash,
      customIntegrationConfigPreserved: true,
      legacySourcePatch:
        "Fixture bootstrap storage/mail and installer Compose isolation only; src/config/composer.json remain archived predecessor bytes.",
      stages: [
        "legacy-enabled",
        "legacy-disabled",
        "legacy-uninstalled",
        "canonical-uninstalled",
        "canonical-enabled",
        "canonical-disabled",
        "canonical-reenabled",
        "canonical-optout",
        "canonical-restored",
      ],
      limits: [
        "Known installed/enabled legacy source available; no forced uninstall or missing-vendor recovery proved.",
        "No original database/schema upgrade or accepted native accounts; no dependency upgrade.",
      ],
    },
    null,
    2,
  ),
);
// Keep useful runtime and database evidence; stop only this proof's services.
run(
  "database-stop",
  "docker",
  [
    "compose",
    "-f",
    path.join(evidence, "legacy-install/compose.yaml"),
    "-p",
    composeProject,
    "down",
  ],
  root,
);
console.log(`Identity migration proved in ${application}`);
