import { chromium, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { mkdirSync, readFileSync, copyFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const runtime = `${root}/tests/e2e/.runtime`;
// Historical released-alpha.20 reproducer. Current Vue Preview acceptance lives
// in editor-preview.spec.ts, including the unmapped native Twig control.
const cmsPackage = JSON.parse(
  readFileSync(`${runtime}/playground/composer.lock`, "utf8"),
).packages.find((entry) => entry.name === "craftcms/cms");
if (cmsPackage?.version !== "6.0.0-alpha.20") {
  throw new Error(
    "This diagnostic requires the released alpha.20 runtime. Use the current editor-preview project for the pinned official 6.x baseline.",
  );
}
const evidence = process.env.POC_E2E_REPORT_DIR ?? `${root}/reports/e2e/step01`;
mkdirSync(evidence, { recursive: true });
const templatePath = `${runtime}/playground/resources/views/e2e/canonical.twig`;
mkdirSync(`${runtime}/playground/resources/views/e2e`, { recursive: true });
copyFileSync(`${root}/tests/e2e/fixtures/canonical.twig`, templatePath);
const output = execFileSync(
  "php",
  [`${root}/tests/e2e/fixtures.php`, "create-native-preview-case"],
  {
    cwd: `${runtime}/playground`,
    encoding: "utf8",
  },
);
const fixture = JSON.parse(output.split("POC_FIXTURES_JSON=").at(-1));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);
const browser = await chromium.launch({ channel: "chrome", headless: true });
try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = [];
  const editorResponses = [];
  const responseReads = [];
  page.on("response", (response) => {
    if (new URL(response.url()).pathname.endsWith("/actions/elements/edit")) {
      responseReads.push(
        response.json().then((data) => {
          for (const match of (data.bodyHtml ?? "").matchAll(
            /Craft\.BaseElementSelectInput\(\{"id":"([^"]+)"/g,
          )) {
            editorResponses.push({
              id: match[1],
              inContent: (data.content ?? "").includes(`id="${match[1]}"`),
              inSidebar: (data.sidebar ?? "").includes(`id="${match[1]}"`),
            });
          }
        }),
      );
    }
  });
  const pluginRequests = [];
  const projectBundleRequests = [];
  page.on("pageerror", (error) =>
    errors.push({ message: error.message, stack: error.stack }),
  );
  context.on("request", (request) => {
    if (request.url().includes("/vendor/robuust/craft-inertia-poc/"))
      pluginRequests.push(request.url());
    if (
      request.frame().parentFrame() &&
      new URL(request.url()).pathname.startsWith("/build/assets/")
    )
      projectBundleRequests.push(request.url());
  });
  await page.goto("http://127.0.0.1:18766/admin/login");
  await page.locator("input[name=username]").fill(credentials.username);
  await page.locator("input[name=password]").fill(credentials.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await page.waitForURL((url) => !url.pathname.endsWith("/login"));
  const idSlug = new URL(fixture.editUrl).pathname.split("/").at(-1);
  const response = await page.goto(
    `http://127.0.0.1:18766/admin/preview/${idSlug}`,
  );
  const frame = page.frameLocator("iframe");
  await expect(
    frame.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
  await page.locator('input[name$="[title]"]').fill(fixture.after);
  await page.locator('input[name$="[title]"]').press("Tab");
  await expect(frame.getByRole("heading", { name: fixture.after })).toBeVisible(
    { timeout: 30_000 },
  );
  await page.screenshot({
    path: `${evidence}/native-craft-baseline.png`,
    fullPage: true,
  });
  await Promise.all(responseReads);
  const result = {
    httpStatus: response.status(),
    primaryTarget: "Native Twig template in an unmapped section",
    projectRequestScope: "Preview iframe only",
    autoRefreshWorked: true,
    pluginRequests,
    projectBundleRequests,
    errors,
    editorResponses,
  };
  writeFileSync(
    `${evidence}/native-craft-baseline.json`,
    JSON.stringify(result, null, 2),
  );
  console.log(JSON.stringify(result, null, 2));
} finally {
  await browser.close();
}
