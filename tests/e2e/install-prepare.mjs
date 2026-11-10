import { execFileSync } from "node:child_process";
import { createHash, randomBytes } from "node:crypto";
import {
  copyFileSync,
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  writeFileSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const fixture = path.join(root, "tests/e2e/installation");
const application = path.join(root, "tests/e2e/.runtime/installation");
const source =
  process.env.POC_PLAYGROUND_PATH ?? path.resolve(root, "../craft6-playground");
const evidence = process.env.POC_INSTALL_REPORT_DIR;
if (!evidence || !path.isAbsolute(evidence)) {
  throw new Error(
    "Set POC_INSTALL_REPORT_DIR to an absolute task-owned evidence directory.",
  );
}
if (existsSync(application)) {
  throw new Error(
    "Installation fixture already exists. Stop its services and archive it before preparing another clean install.",
  );
}
const hash = (file) =>
  createHash("sha256").update(readFileSync(file)).digest("hex");
const baseline = JSON.parse(
  readFileSync(new URL("./official-craft.json", import.meta.url)),
);
const sourceLock = JSON.parse(readFileSync(path.join(source, "composer.lock")));
function assertPins(lock) {
  for (const pin of baseline.pins) {
    const pkg = lock.packages.find(({ name }) => name === pin.name);
    if (
      pkg?.source?.reference !== pin.reference ||
      pkg?.dist?.reference !== pin.reference
    ) {
      throw new Error(
        `Expected the approved source and dist pin for ${pin.name}.`,
      );
    }
  }
}
assertPins(sourceLock);
mkdirSync(path.join(evidence, "logs"), { recursive: true });
cpSync(fixture, application, { recursive: true });
for (const directory of [
  "bootstrap/cache",
  "storage/framework/cache/data",
  "storage/framework/sessions",
  "storage/framework/views",
  "storage/logs",
  "public",
]) {
  mkdirSync(path.join(application, directory), { recursive: true });
}
function run(name, command, args, options = {}) {
  console.log(name);
  try {
    const output = execFileSync(command, args, {
      cwd: application,
      encoding: "utf8",
      stdio: "pipe",
      ...options,
    });
    writeFileSync(path.join(evidence, "logs", `${name}.log`), output);
    return output.trim();
  } catch (error) {
    writeFileSync(
      path.join(evidence, "logs", `${name}.log`),
      `${error.stdout ?? ""}\n${error.stderr ?? ""}`,
    );
    throw new Error(`${name} failed; inspect its task log.`, { cause: error });
  }
}

// Reuse only approved dependency metadata: no application code, config, DB or content.
const composer = JSON.parse(readFileSync(path.join(source, "composer.json")));
composer.name = "local/clean-inertia-installation";
composer.description = "Isolated clean installation acceptance fixture";
composer.scripts = {};
composer.repositories = [
  {
    type: "path",
    url: root,
    options: {
      symlink: true,
      versions: { "robuust/craft-inertia-poc": "0.1.0" },
    },
  },
  ...baseline.repositories,
];
writeFileSync(
  path.join(application, "composer.json"),
  JSON.stringify(composer, null, 4),
);
copyFileSync(
  path.join(source, "composer.lock"),
  path.join(application, "composer.lock"),
);
for (const file of ["package.json", "yarn.lock"]) {
  copyFileSync(path.join(source, file), path.join(application, file));
}

const composeFile = path.join(evidence, "compose.yaml");
writeFileSync(
  composeFile,
  `services:
  postgres:
    image: postgres@sha256:e38411452a464af89e5adadb8d223bf53b898d47d6ef918b2d58c08707350449
    environment:
      POSTGRES_DB: clean_installation
      POSTGRES_USER: clean_installation
      POSTGRES_PASSWORD: isolated-only
    ports:
      - "127.0.0.1::5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U clean_installation -d clean_installation"]
      interval: 1s
      timeout: 3s
      retries: 30
`,
);
const compose = [
  "compose",
  "-f",
  composeFile,
  "-p",
  "craft-inertia-installation",
];
run("database-start", "docker", [...compose, "up", "--wait", "-d"]);
const tables = run("database-empty", "docker", [
  ...compose,
  "exec",
  "-T",
  "postgres",
  "psql",
  "-U",
  "clean_installation",
  "-d",
  "clean_installation",
  "-Atc",
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'",
]);
if (tables !== "0")
  throw new Error("Clean installation requires an empty task-owned database.");
const port = run("database-port", "docker", [
  ...compose,
  "port",
  "postgres",
  "5432",
])
  .split(":")
  .at(-1);
const password = randomBytes(24).toString("hex");
const environment = {
  APP_NAME: "Clean installation",
  APP_ENV: "local",
  APP_DEBUG: "false",
  APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
  APP_URL: "http://127.0.0.1:18777",
  DEFAULT_SITE_URL: "http://127.0.0.1:18777",
  DB_CONNECTION: "pgsql",
  DB_HOST: "127.0.0.1",
  DB_PORT: port,
  DB_DATABASE: "clean_installation",
  DB_USERNAME: "clean_installation",
  DB_PASSWORD: "isolated-only",
  SESSION_DRIVER: "file",
  CACHE_STORE: "file",
  QUEUE_CONNECTION: "sync",
  MAIL_MAILER: "log",
  LOG_CHANNEL: "single",
  FRONTEND_ENABLED: "false",
  INERTIA_SSR_ENABLED: "true",
  INERTIA_SSR_URL: "http://127.0.0.1:13727",
  INERTIA_SSR_THROW_ON_ERROR: "true",
};
writeFileSync(
  path.join(application, ".env"),
  Object.entries(environment)
    .map(([key, value]) => `${key}=${JSON.stringify(value)}\n`)
    .join(""),
  { mode: 0o600 },
);
run("composer-install", "composer", [
  "update",
  "robuust/craft-inertia-poc",
  "--no-interaction",
  "--no-scripts",
  "--prefer-dist",
]);
const lock = JSON.parse(readFileSync(path.join(application, "composer.lock")));
assertPins(lock);
const withoutPlugin = (input) =>
  [...input.packages, ...input["packages-dev"]].filter(
    ({ name }) => name !== "robuust/craft-inertia-poc",
  );
if (
  JSON.stringify(withoutPlugin(lock)) !==
  JSON.stringify(withoutPlugin(sourceLock))
) {
  throw new Error(
    "A dependency other than the local plugin changed from the approved root lock.",
  );
}
copyFileSync(
  path.join(application, "vendor/craftcms/cms/src/Console/craft.stub"),
  path.join(application, "artisan"),
);
writeFileSync(
  path.join(application, "public/index.php"),
  `<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->handleRequest(\\Illuminate\\Http\\Request::capture());
`,
);
run("package-discovery", "php", [
  "artisan",
  "package:discover",
  "--no-interaction",
]);
run(
  "craft-install",
  "php",
  [
    "artisan",
    "craft:install",
    "--email=owner@installation.test",
    "--username=installation-owner",
    `--password=${password}`,
    "--siteName=Clean installation",
    "--siteUrl=$APP_URL",
    "--language=en",
    "--no-interaction",
  ],
  { env: { ...process.env, APP_ENV: "install" } },
);
run("plugin-install", "php", [
  "artisan",
  "craft:plugin:install",
  "inertia-poc",
  "--no-interaction",
]);
run("plugin-config", "php", [
  "artisan",
  "vendor:publish",
  "--tag=inertia-poc-config",
  "--no-interaction",
]);
run("native-assets", "php", [
  "artisan",
  "craft:setup:publish",
  "--no-interaction",
]);
run("frontend-install", "corepack", ["yarn", "install", "--immutable"]);
const frontendNode = run("frontend-node", "corepack", [
  "yarn",
  "node",
  "--version",
]);
run("build-client", "corepack", ["yarn", "vite", "build"]);
run("build-ssr", "corepack", ["yarn", "vite", "build", "--ssr"]);
function manifest(directory) {
  const result = {};
  function visit(current, prefix = "") {
    for (const entry of readdirSync(current, { withFileTypes: true })) {
      const relative = path.join(prefix, entry.name);
      if (entry.isDirectory()) visit(path.join(current, entry.name), relative);
      else if (entry.isFile())
        result[relative] = hash(path.join(current, entry.name));
    }
  }
  visit(directory);
  return Object.fromEntries(
    Object.entries(result).sort(([a], [b]) => a.localeCompare(b, "en")),
  );
}
const native = {};
for (const pin of baseline.pins) {
  const directory = path.join(application, "vendor", pin.name);
  if (hash(path.join(directory, "composer.json")) !== pin.manifestSha256)
    throw new Error("Native manifest changed.");
  native[pin.name] = manifest(directory);
}
const publishMap = JSON.parse(
  run("native-publish-map", "php", [
    "artisan",
    "tinker",
    '--execute=echo json_encode(\\Illuminate\\Support\\ServiceProvider::pathsToPublish(null, "craftcms-assets"), JSON_THROW_ON_ERROR);',
  ]),
);
const published = manifest(path.join(application, "public/vendor/craft"));
const expected = {};
for (const [from, to] of Object.entries(publishMap)) {
  const prefix = path.relative(
    path.join(application, "public/vendor/craft"),
    to,
  );
  if (prefix.startsWith("..")) throw new Error("Unexpected native asset path.");
  for (const [file, digest] of Object.entries(manifest(from)))
    expected[path.join(prefix, file)] = digest;
}
if (
  JSON.stringify(published) !==
  JSON.stringify(
    Object.fromEntries(
      Object.entries(expected).sort(([a], [b]) => a.localeCompare(b, "en")),
    ),
  )
)
  throw new Error("Published native assets changed.");
const plugin = {};
for (const directory of ["src", "config"]) {
  plugin[directory] = manifest(
    path.join(application, "vendor/robuust/craft-inertia-poc", directory),
  );
  if (
    JSON.stringify(plugin[directory]) !==
    JSON.stringify(manifest(path.join(root, directory)))
  )
    throw new Error("Installed plugin source changed.");
}
writeFileSync(
  path.join(evidence, "dependency-files.json"),
  JSON.stringify({ native, published, plugin }, null, 2),
);
writeFileSync(
  path.join(evidence, "installation.json"),
  JSON.stringify(
    {
      application,
      nodeVersion: process.version,
      frontendNode,
      pins: baseline.pins,
      nativePublishMap: publishMap,
      sourceComposerSha256: hash(path.join(source, "composer.json")),
      sourceLockSha256: hash(path.join(source, "composer.lock")),
      lockSha256: hash(path.join(application, "composer.lock")),
      yarnLockSha256: hash(path.join(application, "yarn.lock")),
      fixture: manifest(fixture),
      sourceMetadataOnly: true,
      dependencyChanges: ["robuust/craft-inertia-poc"],
      packages: [...lock.packages, ...lock["packages-dev"]].map(
        ({ name, version, source: origin }) => ({
          name,
          version,
          reference: origin?.reference,
        }),
      ),
    },
    null,
    2,
  ),
);
copyFileSync(
  path.join(application, "composer.lock"),
  path.join(evidence, "runtime-composer.lock"),
);
console.log(
  `Clean installation ready at ${application}; project opt-in defaults off.`,
);
