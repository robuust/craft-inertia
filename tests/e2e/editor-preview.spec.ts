import { test, expect } from "@playwright/test";
import type { Browser, Page, TestInfo } from "@playwright/test";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);
function fixtureCommand(...args: string[]) {
  const output = execFileSync(
    "php",
    [fileURLToPath(new URL("./fixtures.php", import.meta.url)), ...args],
    {
      cwd: `${runtime}/playground`,
      encoding: "utf8",
      stdio: "pipe",
    },
  );
  return JSON.parse(output.split("POC_FIXTURES_JSON=").at(-1)!);
}
async function login(page: Page) {
  await page.goto("/admin/login");
  await page.locator("input[name=username]").fill(credentials.username);
  await page.locator("input[name=password]").fill(credentials.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).not.toHaveURL(/\/admin\/login/);
}
const browserErrors = new WeakMap<Page, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  browserErrors.set(page, errors);
  page.on("pageerror", (error) => errors.push(error.message));
  page
    .context()
    .on("page", (popup) =>
      popup.on("pageerror", (error) => errors.push(error.message)),
    );
});
test.afterEach(async ({ page }, testInfo) => {
  const errors = browserErrors.get(page) ?? [];
  await testInfo.attach("javascript-errors", {
    body: JSON.stringify(errors),
    contentType: "application/json",
  });
  expect(errors, "No unhandled browser JavaScript errors").toEqual([]);
});

async function autosavePreview(
  page: Page,
  browser: Browser,
  fixture: any,
  mode: string,
  testInfo: TestInfo,
  twig = false,
) {
  test.setTimeout(90_000);
  const iframeResponses: { code: number; cacheControl: string }[] = [];
  const projectBundles: string[] = [];
  page.on("response", (response) => {
    const request = response.request();
    if (request.isNavigationRequest() && request.frame().parentFrame()) {
      iframeResponses.push({
        code: response.status(),
        cacheControl: response.headers()["cache-control"] ?? "",
      });
    }
  });
  page.on("request", (request) => {
    if (
      request.frame().parentFrame() &&
      new URL(request.url()).pathname.startsWith("/build/assets/")
    )
      projectBundles.push(request.url());
  });
  await login(page);
  if (mode === "ordinary") {
    await page.goto(fixture.editUrl);
    await expect(page.locator('input[name="title"]')).toHaveValue(
      fixture.before,
    );
    await page
      .getByRole("button", { name: "Preview", exact: true })
      .and(page.locator("#main craft-button"))
      .click();
  } else {
    const idSlug = new URL(fixture.editUrl).pathname.split("/").at(-1);
    await page.goto(`/admin/preview/${idSlug}?site=${fixture.handle}`);
  }
  const editor = page.locator(".lp-editor-pane");
  const iframe = page.locator('iframe[title="Preview"].lp-frame--ready');
  await expect(iframe).toBeVisible();
  const frame = page.frameLocator('iframe[title="Preview"].lp-frame--ready');
  await expect(
    frame.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
  await editor.locator('input[name="title"]').fill(fixture.after);
  await editor
    .locator('textarea[name="fields[summary]"]')
    .fill(`${fixture.after} summary`);
  await editor
    .locator('textarea[name="fields[body]"]')
    .fill(`${fixture.after} body`);
  await editor.locator('textarea[name="fields[body]"]').press("Tab");
  await expect(frame.getByRole("heading", { name: fixture.after })).toBeVisible(
    { timeout: 30_000 },
  );
  await expect(
    frame.getByText(`${fixture.after} summary`, { exact: true }),
  ).toBeVisible();
  await expect(
    frame.getByText(`${fixture.after} body`, { exact: true }),
  ).toBeVisible();
  if (!twig)
    await expect(frame.getByRole("status")).toContainText("Live Preview");
  const visitor = await browser.newContext();
  try {
    const publicPage = await visitor.newPage();
    await publicPage.goto(fixture.url);
    await expect(
      publicPage.getByRole("heading", { name: fixture.before }),
    ).toBeVisible();
    await expect(publicPage.locator("body")).not.toContainText(
      `${fixture.after} body`,
    );
  } finally {
    await visitor.close();
  }
  const previewUrl = new URL((await iframe.getAttribute("src"))!);
  expect(previewUrl.origin).toBe(new URL(fixture.url).origin);
  expect(previewUrl.pathname).toBe(new URL(fixture.url).pathname);
  expect(previewUrl.searchParams.has("token")).toBe(true);
  expect(previewUrl.searchParams.has("x-craft-live-preview")).toBe(true);
  expect(iframeResponses.length).toBeGreaterThanOrEqual(2);
  expect(iframeResponses.every((response) => response.code === 200)).toBe(true);
  if (!twig) {
    expect(
      iframeResponses.every(
        (response) =>
          response.cacheControl.includes("private") &&
          response.cacheControl.includes("no-store"),
      ),
    ).toBe(true);
  } else {
    expect(projectBundles).toEqual([]);
  }
  await testInfo.attach("native-preview", {
    body: JSON.stringify({
      mode,
      site: fixture.handle,
      origin: previewUrl.origin,
      pathname: previewUrl.pathname,
      queryKeys: [...previewUrl.searchParams.keys()],
      iframeResponses,
      projectBundles: projectBundles.length,
    }),
    contentType: "application/json",
  });
}

test("Craft editor View opens the primary native URL for an authorized named draft", async ({
  page,
}) => {
  const fixture = fixtureCommand("create-publish-case");
  await login(page);
  await page.goto(fixture.draftEditUrl);
  await expect(page.locator("input[name=title]")).toHaveValue(fixture.after);
  const popupPromise = page.waitForEvent("popup");
  await page.getByRole("link", { name: /^View Opens in a new window/ }).click();
  const preview = await popupPromise;
  await expect(
    preview.getByRole("heading", { name: fixture.after }),
  ).toBeVisible();
  await expect(preview.getByRole("status")).toContainText("Live Preview");
  await expect(preview.locator("main article .whitespace-pre-wrap")).toHaveText(
    "Published from named draft",
  );
  await preview.close();
  await page.goto(fixture.url);
  await expect(
    page.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
});
for (const handle of ["default", "dutch", "dutchDomain"]) {
  for (const mode of ["ordinary", "standalone"]) {
    test(`${handle}: ${mode} Vue preview autosaves fields without publishing or browser errors`, async ({
      page,
      browser,
    }, testInfo) => {
      const fixture = fixtureCommand("create-site-preview-case", handle);
      await autosavePreview(page, browser, fixture, mode, testInfo);
    });
  }
}
test("native Twig control autosaves through the same Vue Preview without project bundles or browser errors", async ({
  page,
  browser,
}, testInfo) => {
  const fixture = {
    ...fixtureCommand("create-native-preview-case"),
    handle: "default",
  };
  await autosavePreview(page, browser, fixture, "ordinary", testInfo, true);
});
