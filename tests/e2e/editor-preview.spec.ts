import { test, expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);

function fixtureCommand(mode: string) {
  const output = execFileSync(
    "php",
    [fileURLToPath(new URL("./fixtures.php", import.meta.url)), mode],
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

test("Craft editor opens the primary native URL for an authorized named draft", async ({
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

test("native Live Preview refreshes title and fields without publishing or browser errors", async ({
  page,
  browser,
}, testInfo) => {
  test.setTimeout(90_000);
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const fixture = fixtureCommand("create-preview-case");
  await login(page);
  const idSlug = new URL(fixture.editUrl).pathname.split("/").at(-1);
  const response = await page.goto(`/admin/preview/${idSlug}`);
  expect(response?.status()).toBe(200);
  const frame = page.frameLocator("iframe");
  await expect(
    frame.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
  await page.locator('input[name$="[title]"]').fill(fixture.after);
  await page
    .locator('textarea[name$="[fields][summary]"]')
    .fill("Live preview summary change");
  await page
    .locator('textarea[name$="[fields][body]"]')
    .fill("Live preview body change");
  await page.locator('textarea[name$="[fields][body]"]').press("Tab");
  await expect(frame.getByRole("heading", { name: fixture.after })).toBeVisible(
    { timeout: 30_000 },
  );
  await expect(
    frame.getByText("Live preview summary change", { exact: true }),
  ).toBeVisible();
  await expect(frame.locator("main article .whitespace-pre-wrap")).toHaveText(
    "Live preview body change",
  );
  await expect(frame.getByRole("status")).toContainText("Live Preview");
  const publicContext = await browser.newContext();
  try {
    const publicPage = await publicContext.newPage();
    await publicPage.goto(fixture.url);
    await expect(
      publicPage.getByRole("heading", { name: fixture.before }),
    ).toBeVisible();
    await expect(publicPage.locator("main")).not.toContainText(
      "Live preview body change",
    );
  } finally {
    await publicContext.close();
  }
  await testInfo.attach("javascript-errors", {
    body: JSON.stringify(errors, null, 2),
    contentType: "application/json",
  });
  expect(
    errors,
    "No unhandled browser JavaScript errors; native Craft baseline remains tracked separately",
  ).toEqual([]);
});

for (const handle of ["dutch", "dutchDomain"]) {
  test(`${handle}: legacy autosave refreshes the native site preview without browser errors`, async ({
    page,
    browser,
  }, testInfo) => {
    test.setTimeout(90_000);
    const site = JSON.parse(readFileSync(`${runtime}/sites.json`, "utf8"))
      .sites[handle];
    const errors: string[] = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await login(page);
    const slug = new URL(site.article.url).pathname.split("/").at(-1);
    await page.goto(`/admin/preview/${site.article.id}-${slug}?site=${handle}`);
    const frame = page.frameLocator("iframe");
    await expect(
      frame.getByRole("heading", { name: site.article.title }),
    ).toBeVisible();
    const changedTitle = `${site.label} autosave secret`;
    await page.locator('input[name$="[title]"]').fill(changedTitle);
    await page
      .locator('textarea[name$="[fields][summary]"]')
      .fill(`${site.label} autosave summary`);
    await page
      .locator('textarea[name$="[fields][body]"]')
      .fill(`${site.label} autosave body`);
    await page.locator('textarea[name$="[fields][body]"]').press("Tab");
    await expect(
      frame.getByRole("heading", { name: changedTitle }),
    ).toBeVisible({ timeout: 30_000 });
    await expect(
      frame.getByText(`${site.label} autosave summary`, { exact: true }),
    ).toBeVisible();
    await expect(frame.locator("main article .whitespace-pre-wrap")).toHaveText(
      `${site.label} autosave body`,
    );
    await expect(frame.getByRole("status")).toContainText("Live Preview");
    const visitor = await browser.newContext();
    try {
      const publicPage = await visitor.newPage();
      await publicPage.goto(site.article.url);
      await expect(
        publicPage.getByRole("heading", { name: site.article.title }),
      ).toBeVisible();
      await expect(publicPage.locator("main")).not.toContainText(
        `${site.label} autosave body`,
      );
    } finally {
      await visitor.close();
    }
    await testInfo.attach("javascript-errors", {
      body: JSON.stringify(errors),
      contentType: "application/json",
    });
    expect(errors, "No unhandled browser JavaScript errors").toEqual([]);
  });
}
