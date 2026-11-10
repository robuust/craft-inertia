import { execFileSync } from "node:child_process";
import { createHash, randomBytes } from "node:crypto";
import {
  copyFileSync,
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const runtime = path.join(root, "tests/e2e/.runtime");
const playground = path.join(runtime, "playground");
const source =
  process.env.POC_PLAYGROUND_PATH ?? path.resolve(root, "../craft6-playground");
const officialBaseline = JSON.parse(
  readFileSync(new URL("./official-craft.json", import.meta.url), "utf8"),
);
function assertPinnedCraft(lock) {
  for (const pin of officialBaseline.pins) {
    const dependency = lock.packages.find((entry) => entry.name === pin.name);
    if (
      dependency?.source?.reference !== pin.reference ||
      dependency?.dist?.reference !== pin.reference
    ) {
      throw new Error(
        `The root lock must pin ${pin.name} source and dist to ${pin.reference}; see the official STEP-03 baseline documentation.`,
      );
    }
  }
}
assertPinnedCraft(
  JSON.parse(readFileSync(path.join(source, "composer.lock"), "utf8")),
);
const compose = [
  "compose",
  "-f",
  path.join(root, "tests/e2e/compose.yaml"),
  "-p",
  "craft-inertia-poc-e2e",
];
const evidence =
  process.env.POC_E2E_REPORT_DIR ?? path.join(root, "reports/e2e/step03");
const logs = path.join(evidence, "logs");
const fresh = process.argv.includes("--fresh");
const workingTree = process.argv.includes("--working-tree");
mkdirSync(logs, { recursive: true });

function run(name, command, args, options = {}) {
  console.log(name);
  try {
    const output = execFileSync(command, args, {
      cwd: playground,
      encoding: "utf8",
      stdio: "pipe",
      ...options,
    });
    writeFileSync(path.join(logs, `${name}.log`), output);
    return output.trim();
  } catch (error) {
    writeFileSync(
      path.join(logs, `${name}.log`),
      `${error.stdout ?? ""}\n${error.stderr ?? ""}`,
    );
    throw new Error(`${name} failed; see ${path.join(logs, `${name}.log`)}`, {
      cause: error,
    });
  }
}

const localChanges = execFileSync("git", ["status", "--porcelain"], {
  cwd: source,
  encoding: "utf8",
}).trim();
if (localChanges && !workingTree) {
  throw new Error(
    "The source Playground has local changes. Inspect them, then pass --working-tree to snapshot the current files without committing them.",
  );
}
if (existsSync(runtime) && !fresh) {
  throw new Error(
    "E2E runtime exists. Stop its PHP/SSR servers and use --fresh to reset only this isolated environment.",
  );
}
if (fresh) {
  run("database-reset", "docker", [...compose, "down", "--volumes"], {
    cwd: root,
  });
  rmSync(runtime, { recursive: true, force: true });
}
mkdirSync(playground, { recursive: true });
const projectHashes = {};
if (workingTree) {
  const files = execFileSync(
    "git",
    ["ls-files", "-z", "--cached", "--others", "--exclude-standard"],
    { cwd: source, encoding: "utf8" },
  )
    .split("\0")
    .filter(Boolean);
  for (const relative of new Set(files)) {
    const current = path.join(source, relative);
    if (!existsSync(current)) continue;
    const target = path.join(playground, relative);
    mkdirSync(path.dirname(target), { recursive: true });
    cpSync(current, target);
    projectHashes[relative] = createHash("sha256")
      .update(readFileSync(current))
      .digest("hex");
  }
} else {
  const archive = path.join(runtime, "playground.tar");
  execFileSync("git", ["archive", "--format=tar", "-o", archive, "HEAD"], {
    cwd: source,
  });
  execFileSync("tar", ["-xf", archive, "-C", playground]);
  rmSync(archive);
}
for (const folder of [
  "storage/framework/cache/data",
  "storage/framework/sessions",
  "storage/framework/views",
  "storage/logs",
  "bootstrap/cache",
  "public",
]) {
  mkdirSync(path.join(playground, folder), { recursive: true });
}
mkdirSync(path.join(playground, "resources/views/e2e"), { recursive: true });
copyFileSync(
  path.join(root, "tests/e2e/fixtures/canonical.twig"),
  path.join(playground, "resources/views/e2e/canonical.twig"),
);
copyFileSync(
  path.join(root, "tests/e2e/fixtures/collision.blade.php"),
  path.join(playground, "resources/views/e2e/collision.blade.php"),
);
copyFileSync(
  path.join(root, "tests/e2e/fixtures/routing.php"),
  path.join(playground, "routes/e2e.php"),
);
const webRoutes = path.join(playground, "routes/web.php");
writeFileSync(
  webRoutes,
  `${readFileSync(webRoutes, "utf8")}\nrequire __DIR__.'/e2e.php';\n`,
);

// This is a fresh test database, not a migration of the source app's older
// project-config schema. Let the pinned native installer create its own schema.
const sourceProjectConfig = path.join(playground, "config/craft/project");
if (existsSync(sourceProjectConfig)) {
  cpSync(sourceProjectConfig, path.join(evidence, "source-project-config"), {
    recursive: true,
  });
  rmSync(sourceProjectConfig, { recursive: true });
}

copyFileSync(
  path.join(root, "tests/e2e/fixtures/ContentQueryTest.php"),
  path.join(playground, "tests/Feature/ContentQueryTest.php"),
);
copyFileSync(
  path.join(root, "tests/e2e/fixtures/PublicContentCacheTest.php"),
  path.join(playground, "tests/Feature/PublicContentCacheTest.php"),
);

const hashes = {};
function hashFiles(directory) {
  if (!existsSync(path.join(root, directory))) return;
  for (const entry of readdirSync(path.join(root, directory), {
    withFileTypes: true,
  })) {
    const relative = path.join(directory, entry.name);
    if (entry.isDirectory()) hashFiles(relative);
    else if (entry.isFile())
      hashes[relative] = createHash("sha256")
        .update(readFileSync(path.join(root, relative)))
        .digest("hex");
  }
}
for (const directory of ["src", "routes", "config", "resources"])
  hashFiles(directory);
writeFileSync(
  path.join(runtime, "source-manifest.json"),
  JSON.stringify(hashes, null, 2),
);
writeFileSync(
  path.join(evidence, "source-manifest.json"),
  JSON.stringify(hashes, null, 2),
);
const commit = execFileSync("git", ["rev-parse", "HEAD"], {
  cwd: source,
  encoding: "utf8",
}).trim();
writeFileSync(
  path.join(runtime, "playground-source.json"),
  JSON.stringify(
    { source, commit, workingTree, localChanges, hashes: projectHashes },
    null,
    2,
  ),
);

run("database-start", "docker", [...compose, "up", "-d", "--wait"], {
  cwd: root,
});
const address = execFileSync(
  "docker",
  [...compose, "port", "postgres", "5432"],
  { cwd: root, encoding: "utf8" },
).trim();
const databasePort = address.split(":").at(-1);
const credentials = {
  email: "admin@inertia-poc.test",
  username: "poc-admin",
  password: `Poc-E2E-${randomBytes(12).toString("hex")}`,
};
writeFileSync(
  path.join(runtime, "credentials.json"),
  JSON.stringify(credentials),
  { mode: 0o600 },
);
const environment = {
  APP_NAME: "Inertia POC E2E",
  APP_ENV: "e2e",
  APP_DEBUG: "true",
  APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
  APP_URL: "http://127.0.0.1:18766",
  DEFAULT_SITE_URL: "http://127.0.0.1:18766",
  APP_LOCALE: "en",
  APP_FALLBACK_LOCALE: "en",
  DB_CONNECTION: "pgsql",
  DB_HOST: "127.0.0.1",
  DB_PORT: databasePort,
  DB_DATABASE: "inertia_poc_e2e",
  DB_USERNAME: "inertia_poc_e2e",
  DB_PASSWORD: "local-e2e-only",
  CACHE_STORE: "file",
  SESSION_DRIVER: "file",
  SESSION_SECURE_COOKIE: "false",
  QUEUE_CONNECTION: "sync",
  MAIL_MAILER: "log",
  LOG_CHANNEL: "single",
  INERTIA_SSR_ENABLED: "true",
  INERTIA_SSR_THROW_ON_ERROR: "true",
  CRAFT_ENVIRONMENT: "e2e",
  BOOST_BROWSER_LOGS_WATCHER: "false",
  APP_EMAIL: credentials.email,
  APP_USER: credentials.username,
  APP_PASSWORD: credentials.password,
};
writeFileSync(
  path.join(playground, ".env"),
  Object.entries(environment)
    .map(([key, value]) => `${key}=${JSON.stringify(value)}\n`)
    .join(""),
  { mode: 0o600 },
);
const composerPath = path.join(playground, "composer.json");
const composer = JSON.parse(readFileSync(composerPath, "utf8"));
composer.repositories ??= [];
composer.repositories = composer.repositories.filter(
  (repository) =>
    repository.type !== "path" || !repository.url.includes("craft-inertia"),
);
composer.repositories.unshift({
  type: "path",
  url: root,
  options: {
    symlink: true,
    versions: { "robuust/craft-inertia-poc": "0.1.0" },
  },
});
composer.require["robuust/craft-inertia-poc"] = "0.1.0";
writeFileSync(composerPath, JSON.stringify(composer, null, 4));

run("composer-install", "composer", [
  "update",
  "robuust/craft-inertia-poc",
  "--no-interaction",
  "--no-scripts",
  "--prefer-dist",
]);
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
    `--email=${credentials.email}`,
    `--username=${credentials.username}`,
    `--password=${credentials.password}`,
    "--siteName=Inertia POC E2E",
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
run("craft-assets", "php", [
  "artisan",
  "craft:setup:publish",
  "--no-interaction",
]);
run("host-dependencies", "corepack", ["yarn", "install", "--immutable"]);
run("host-build", "corepack", ["yarn", "build"]);
const resolvedLock = JSON.parse(
  readFileSync(path.join(playground, "composer.lock"), "utf8"),
);
assertPinnedCraft(resolvedLock);
function fileManifest(directory) {
  const result = {};
  function visit(current, prefix = "") {
    for (const entry of readdirSync(current, { withFileTypes: true })) {
      const relative = path.join(prefix, entry.name);
      const absolute = path.join(current, entry.name);
      if (entry.isDirectory()) visit(absolute, relative);
      else if (entry.isFile())
        result[relative] = createHash("sha256")
          .update(readFileSync(absolute))
          .digest("hex");
    }
  }
  visit(directory);
  return Object.fromEntries(
    Object.entries(result).sort(([a], [b]) => a.localeCompare(b, "en")),
  );
}
const dependencyFiles = {};
for (const pin of officialBaseline.pins) {
  const directory = path.join(playground, "vendor", pin.name);
  const manifestHash = createHash("sha256")
    .update(readFileSync(path.join(directory, "composer.json")))
    .digest("hex");
  if (manifestHash !== pin.manifestSha256)
    throw new Error(
      `Installed ${pin.name} manifest differs from the pinned official source.`,
    );
  dependencyFiles[pin.name] = fileManifest(directory);
}
const publishedAssets = fileManifest(
  path.join(playground, "public/vendor/craft"),
);
const nativePublishMap = JSON.parse(
  run("native-asset-publish-map", "php", [
    "artisan",
    "tinker",
    '--execute=echo json_encode(\\Illuminate\\Support\\ServiceProvider::pathsToPublish(null, "craftcms-assets"), JSON_THROW_ON_ERROR);',
  ]),
);
const expectedPublishedAssets = {};
for (const [from, to] of Object.entries(nativePublishMap)) {
  const prefix = path.relative(
    path.join(playground, "public/vendor/craft"),
    to,
  );
  if (prefix.startsWith(".."))
    throw new Error("Unexpected native CP publication destination.");
  for (const [relative, hash] of Object.entries(fileManifest(from)))
    expectedPublishedAssets[path.join(prefix, relative)] = hash;
}
const orderedExpectedAssets = Object.fromEntries(
  Object.entries(expectedPublishedAssets).sort(([a], [b]) =>
    a.localeCompare(b, "en"),
  ),
);
if (JSON.stringify(publishedAssets) !== JSON.stringify(orderedExpectedAssets)) {
  throw new Error(
    "Published CP assets differ from the pinned package's native publication map.",
  );
}
const identity = {
  pins: officialBaseline.pins,
  nodeVersion: process.version,
  nativePublishMap,
  lockSha256: createHash("sha256")
    .update(readFileSync(path.join(playground, "composer.lock")))
    .digest("hex"),
  sourceLockSha256: createHash("sha256")
    .update(readFileSync(path.join(source, "composer.lock")))
    .digest("hex"),
  sourceComposerSha256: createHash("sha256")
    .update(readFileSync(path.join(source, "composer.json")))
    .digest("hex"),
  packages: resolvedLock.packages.map(({ name, version, source, dist }) => ({
    name,
    version,
    source,
    dist,
  })),
  dependencyTreeSha256: Object.fromEntries(
    Object.entries(dependencyFiles).map(([name, files]) => [
      name,
      createHash("sha256").update(JSON.stringify(files)).digest("hex"),
    ]),
  ),
  publishedAssetTreeSha256: createHash("sha256")
    .update(JSON.stringify(publishedAssets))
    .digest("hex"),
  cpManifestSha256:
    publishedAssets["build/manifest.json"] ??
    publishedAssets["build/.vite/manifest.json"],
};
writeFileSync(
  path.join(evidence, "dependency-identity.json"),
  JSON.stringify(identity, null, 2),
);
writeFileSync(
  path.join(evidence, "dependency-files.json"),
  JSON.stringify({ ...dependencyFiles, publishedAssets }, null, 2),
);
copyFileSync(
  path.join(playground, "composer.lock"),
  path.join(evidence, "runtime-composer.lock"),
);
copyFileSync(
  path.join(source, "composer.lock"),
  path.join(evidence, "source-composer.lock"),
);
copyFileSync(
  path.join(source, "composer.json"),
  path.join(evidence, "source-composer.json"),
);
run("fixture-seed", "php", [path.join(root, "tests/e2e/fixtures.php"), "seed"]);
if (!existsSync(path.join(runtime, "fixtures.json")))
  throw new Error("Fixture metadata was not written.");
run("cache-clear", "php", ["artisan", "optimize:clear", "--no-interaction"]);
run("routes", "php", [
  "artisan",
  "route:list",
  "--name=site",
  "-v",
  "--no-interaction",
]);
run("plugin-list", "php", ["artisan", "craft:plugin:list", "--no-interaction"]);
console.log(`Prepared an isolated Playground snapshot at ${commit}.`);
console.log(
  "Start the PHP server and the host SSR process, then run npm run test:e2e.",
);
