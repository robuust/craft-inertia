import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import { readFileSync } from "node:fs";
const fixtures = JSON.parse(
  readFileSync(
    new URL("./.runtime/entry-templates/entries.json", import.meta.url),
    "utf8",
  ),
);

test("native homepage SSR hydrates and navigates with real Inertia JSON", async ({
  page,
  request,
}) => {
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const html = await request.get("/");
  expect(await html.text()).toContain("<h1>Native home</h1>");
  await page.goto("/");
  await expect(
    page.getByRole("heading", { name: "Native home" }),
  ).toBeVisible();
  const navigation = page.waitForResponse(
    (response) =>
      response.url().endsWith("/articles/first") &&
      response.request().headers()["x-inertia"] === "true",
  );
  await page.getByRole("link", { name: "Read article" }).click();
  const response = await navigation;
  expect(response.headers()["x-inertia"]).toBe("true");
  const data = await response.json();
  expect(data.component).toBe("Articles/Show");
  expect(data.props.entry.fields.summary).toBe("Public summary");
  expect(data.props.entry.fields).not.toHaveProperty("internalNote");
  await expect(
    page.getByRole("heading", { name: "Published current" }),
  ).toBeVisible();
  await expect(page.locator("main img")).toHaveAttribute(
    "alt",
    "Blue content fixture",
  );
  expect(await page.evaluate(() => (window as any).__entryXss)).toBeUndefined();
  await page.getByRole("link", { name: "Home", exact: true }).click();
  await page.getByRole("link", { name: "Read journal" }).click();
  await expect(page.locator("main")).toContainText("Different schema teaser");
  await page.goBack();
  await expect(
    page.getByRole("heading", { name: "Native home" }),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test("native structure URL and second-site identity render directly", async ({
  page,
}) => {
  await page.goto(fixtures.child.url);
  await expect(page.locator("main")).toContainText("Nested place");
  await page.goto(fixtures.dutch.url);
  await expect(
    page.getByRole("heading", { name: "Nederlands artikel" }),
  ).toBeVisible();
  await expect(page.locator("main")).toContainText("nl");
  await expect(page.locator("link[rel=canonical]")).toHaveAttribute(
    "href",
    fixtures.dutch.url,
  );
});

test("native draft and revision previews stay private and client rendered", async ({
  page,
  request,
}) => {
  for (const key of ["draft", "revision"]) {
    const fixture = fixtures[key];
    const url = `${fixture.url}?token=${fixture.token}&x-craft-preview=1`;
    const response = await request.get(url);
    expect(response.status()).toBe(200);
    expect(response.headers()["cache-control"]).toContain("no-store");
    expect(response.headers()["x-robots-tag"]).toMatch(/noindex|none/);
    expect(await response.text()).not.toContain(`<h1>${fixture.title}</h1>`);
    await page.goto(url);
    await expect(
      page.getByRole("heading", { name: fixture.title }),
    ).toBeVisible();
    await expect(page.getByText("Preview", { exact: true })).toBeVisible();
  }
});

test("native CP Live Preview autosaves through the plugin renderer without publishing", async ({
  page,
  browser,
}, testInfo) => {
  test.setTimeout(90_000);
  const credentials = JSON.parse(
    readFileSync(
      new URL("./.runtime/entry-templates/credentials.json", import.meta.url),
      "utf8",
    ),
  );
  const fixture = JSON.parse(
    execFileSync(
      "php",
      [
        fileURLToPath(
          new URL("./entry-template-fixtures.php", import.meta.url),
        ),
        "editor",
      ],
      { encoding: "utf8" },
    ),
  );
  const errors: string[] = [];
  const frames: { status: number; cache: string }[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("response", (response) => {
    if (
      response.request().isNavigationRequest() &&
      response.request().frame().parentFrame()
    ) {
      frames.push({
        status: response.status(),
        cache: response.headers()["cache-control"] ?? "",
      });
    }
  });
  await page.goto("/admin/login");
  await page.locator("input[name=username]").fill(credentials.username);
  await page.locator("input[name=password]").fill(credentials.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await page.waitForURL((url) => !url.pathname.endsWith("/admin/login"), {
    waitUntil: "domcontentloaded",
    timeout: 30_000,
  });
  await page.goto(fixture.editUrl);
  await expect(page.locator("input[name=title]")).toHaveValue(fixture.title);
  await page
    .getByRole("button", { name: "Preview", exact: true })
    .and(page.locator("#main craft-button"))
    .click();
  const iframe = page.locator("iframe[title=Preview].lp-frame--ready");
  await expect(iframe).toBeVisible();
  const preview = page.frameLocator("iframe[title=Preview].lp-frame--ready");
  await expect(
    preview.getByRole("heading", { name: fixture.title }),
  ).toBeVisible();
  const editor = page.locator(".lp-editor-pane");
  await editor.locator("input[name=title]").fill("Edited native preview");
  await editor
    .locator('input[name="fields[body]"]')
    .fill("Autosaved preview body");
  await editor.locator('input[name="fields[body]"]').press("Tab");
  await expect(
    preview.getByRole("heading", { name: "Edited native preview" }),
  ).toBeVisible({ timeout: 30_000 });
  await expect(
    preview.getByText("Autosaved preview body", { exact: true }),
  ).toBeVisible();
  const visitor = await browser.newContext();
  try {
    const publicPage = await visitor.newPage();
    await publicPage.goto(fixture.url);
    await expect(
      publicPage.getByRole("heading", { name: fixture.title }),
    ).toBeVisible();
    await expect(publicPage.locator("body")).not.toContainText(
      "Autosaved preview body",
    );
  } finally {
    await visitor.close();
  }
  const src = new URL((await iframe.getAttribute("src"))!);
  expect(src.searchParams.has("token")).toBe(true);
  expect(src.searchParams.has("x-craft-live-preview")).toBe(true);
  expect(frames.length).toBeGreaterThanOrEqual(2);
  expect(
    frames.every(
      (frame) => frame.status === 200 && frame.cache.includes("no-store"),
    ),
  ).toBe(true);
  expect(errors).toEqual([]);
  await testInfo.attach("native-preview-evidence", {
    body: JSON.stringify({
      frames,
      errors,
      path: src.pathname,
      queryKeys: [...src.searchParams.keys()],
    }),
    contentType: "application/json",
  });
});
