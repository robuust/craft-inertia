import { test, expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const fixtures = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);
const configPath = `${runtime}/playground/config/inertia-poc.php`;
const originalConfig = readFileSync(configPath, "utf8");
const entryUrl = (id: number) => `/inertia-poc/entries/${id}`;
const previewUrl = (fixture: { id: number; token: string }) =>
  `${entryUrl(fixture.id)}?token=${encodeURIComponent(fixture.token)}`;

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

test.beforeEach(async ({ page }) => {
  writeFileSync(configPath, originalConfig);
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  (page as Page & { pocErrors: string[] }).pocErrors = errors;
});

test.afterEach(async ({ page }, testInfo) => {
  writeFileSync(configPath, originalConfig);
  const errors = (page as Page & { pocErrors: string[] }).pocErrors;
  await testInfo.attach("javascript-errors", {
    body: JSON.stringify(errors, null, 2),
    contentType: "application/json",
  });
  expect.soft(errors, "No unhandled browser JavaScript errors").toEqual([]);
});

test("overview shows the latest 20 live entries in date order", async ({
  page,
}) => {
  const response = await page.goto("/inertia-poc");
  expect(response?.status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "Entries", exact: true }),
  ).toBeVisible();
  await expect(page.locator("main .entries li")).toHaveCount(20);
  await expect(page.locator("main .entries h2")).toHaveText(
    fixtures.published
      .slice(-20)
      .reverse()
      .map((entry: { title: string }) => entry.title),
  );
});

test("direct entry visit renders title, summary, multiline body and date", async ({
  page,
}) => {
  const entry = fixtures.published.at(-1);
  const response = await page.goto(entryUrl(entry.id));
  expect(response?.status()).toBe(200);
  await expect(page.getByRole("heading", { name: entry.title })).toBeVisible();
  await expect(
    page.getByText(`Summary for ${entry.title}`, { exact: true }),
  ).toBeVisible();
  await expect(page.locator(".entry-body")).toHaveText(
    `Published body for ${entry.title}\nSecond paragraph.`,
  );
  await expect(page.locator("time")).toHaveAttribute("datetime", "2020-01-23");
  await expect(page).toHaveTitle(
    `${entry.title} · Craft 6 Inertia proof of concept`,
  );
});

test("Inertia navigation and browser Back preserve the JavaScript document", async ({
  page,
}) => {
  await page.goto("/inertia-poc");
  const entry = fixtures.published.at(-1);
  await expect(page.getByRole("link", { name: entry.title })).toBeVisible();
  await page.evaluate(() => {
    (window as unknown as Record<string, unknown>).__pocNavigation =
      "same-document";
  });
  const responsePromise = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname === entryUrl(entry.id) &&
      response.request().headers()["x-inertia"] === "true",
  );
  await page.getByRole("link", { name: entry.title }).click();
  const response = await responsePromise;
  expect(response.headers()["x-inertia"]).toBe("true");
  expect((await response.json()).component).toBe(
    "CraftInertiaPoc/Entries/Show",
  );
  await expect(page.getByRole("heading", { name: entry.title })).toBeVisible();
  expect(
    await page.evaluate(
      () => (window as unknown as Record<string, unknown>).__pocNavigation,
    ),
  ).toBe("same-document");
  await page.goBack();
  await expect(
    page.getByRole("heading", { name: "Entries", exact: true }),
  ).toBeVisible();
  expect(
    await page.evaluate(
      () => (window as unknown as Record<string, unknown>).__pocNavigation,
    ),
  ).toBe("same-document");
});

for (const kind of [
  "disabled",
  "future",
  "expired",
  "otherSection",
  "unpublished",
] as const) {
  test(`${kind} entry stays unavailable publicly`, async ({ page }) => {
    const id =
      kind === "unpublished" ? fixtures.unpublished.id : fixtures[kind];
    const response = await page.goto(entryUrl(id));
    expect(response?.status()).toBe(404);
    await expect(
      page.getByRole("heading", { name: "Entry not found" }),
    ).toBeVisible();
    await expect(page.locator("main")).not.toContainText("secret");
  });
}

test("unknown numeric entry returns the plugin 404 page", async ({ page }) => {
  const response = await page.goto(entryUrl(99999999));
  expect(response?.status()).toBe(404);
  await expect(
    page.getByRole("heading", { name: "Entry not found" }),
  ).toBeVisible();
});

test("missing optional fields render without errors", async ({ page }) => {
  await page.goto(entryUrl(fixtures.minimal));
  await expect(
    page.getByRole("heading", { name: "E2E minimal entry" }),
  ).toBeVisible();
  await expect(page.locator(".entry-body")).toHaveCount(0);
});

test("title, summary and body are escaped rather than executed", async ({
  page,
}) => {
  await page.goto(entryUrl(fixtures.xss));
  await expect(
    page.getByRole("heading", {
      name: '<img src=x onerror="window.__pocXss=1">',
    }),
  ).toBeVisible();
  await expect(page.locator("main")).toContainText(
    "<script>window.__pocXss=2</script>",
  );
  await expect(page.locator(".entry-body")).toHaveText(
    '<img src=x onerror="window.__pocXss=3">',
  );
  await expect(page.locator("main img, main script")).toHaveCount(0);
  expect(
    await page.evaluate(
      () => (window as unknown as Record<string, unknown>).__pocXss,
    ),
  ).toBeUndefined();
});

for (const [section, message] of [
  ["pocEmpty", "No published entries yet."],
  ["pocMissing", "Create a Craft section with handle"],
] as const) {
  test(`${section} section has an actionable empty state`, async ({ page }) => {
    writeFileSync(
      configPath,
      `<?php return ['section' => '${section}', 'summaryField' => 'summary', 'bodyField' => 'body'];\n`,
    );
    const response = await page.goto("/inertia-poc");
    expect(response?.status()).toBe(200);
    await expect(page.getByRole("status")).toContainText(message);
    await expect(page.locator(".entries li")).toHaveCount(0);
  });
}

test("responses are non-cacheable and excluded from indexing", async ({
  page,
}) => {
  const response = await page.goto(entryUrl(fixtures.published.at(-1).id));
  expect(response?.headers()["cache-control"]).toContain("private");
  expect(response?.headers()["cache-control"]).toContain("no-store");
  expect(response?.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
});

test("named draft preview uses a real Craft token and leaves public content intact", async ({
  page,
  browser,
}) => {
  await page.goto(previewUrl(fixtures.namedDraft));
  await expect(
    page.getByRole("heading", { name: "E2E named draft secret" }),
  ).toBeVisible();
  await expect(page.getByRole("status")).toContainText("Live Preview");
  await expect(page.locator(".entry-body")).toHaveText(
    "Named draft body secret",
  );
  const publicContext = await browser.newContext();
  const publicPage = await publicContext.newPage();
  await publicPage.goto(
    `http://127.0.0.1:18766${entryUrl(fixtures.namedDraft.id)}`,
  );
  await expect(
    publicPage.getByRole("heading", { name: "E2E named draft published" }),
  ).toBeVisible();
  await expect(publicPage.locator("main")).not.toContainText("secret");
  await publicContext.close();
});

test("unpublished draft is previewable with a valid Craft token", async ({
  page,
}) => {
  const response = await page.goto(previewUrl(fixtures.unpublished));
  expect(response?.status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "E2E unpublished draft secret" }),
  ).toBeVisible();
  await expect(page.getByRole("status")).toContainText("Live Preview");
});

for (const [name, parameters] of [
  ["invalid token", "token=not-a-valid-preview-token"],
  [
    "forged markers",
    "x-craft-preview=1&x-craft-live-preview=1&draftId=1&status=disabled",
  ],
  [
    "expired token",
    `token=${encodeURIComponent(fixtures.namedDraft.expiredToken)}`,
  ],
] as const) {
  test(`${name} cannot expose named draft content`, async ({ page }) => {
    await page.goto(`${entryUrl(fixtures.namedDraft.id)}?${parameters}`);
    await expect(
      page.getByRole("heading", { name: "E2E named draft published" }),
    ).toBeVisible();
    await expect(page.locator("main")).not.toContainText("secret");
    await expect(page.getByRole("status")).toHaveCount(0);
  });
}

test("a token for one entry does not grant access to another unpublished draft", async ({
  page,
}) => {
  const response = await page.goto(
    `${entryUrl(fixtures.unpublished.id)}?token=${encodeURIComponent(fixtures.namedDraft.token)}`,
  );
  expect(response?.status()).toBe(404);
  await expect(
    page.getByRole("heading", { name: "Entry not found" }),
  ).toBeVisible();
});

test("the overview never renders draft placeholders", async ({ page }) => {
  await page.goto(
    `/inertia-poc?token=${encodeURIComponent(fixtures.namedDraft.token)}`,
  );
  await expect(
    page.getByRole("heading", { name: "Entries", exact: true }),
  ).toBeVisible();
  await expect(page.locator("main")).not.toContainText("secret");
  await expect(page.locator(".entries li")).toHaveCount(20);
});

test("mobile viewport remains readable without horizontal scrolling", async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/inertia-poc");
  await expect(
    page.getByRole("heading", { name: "Entries", exact: true }),
  ).toBeVisible();
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
  ).toBe(true);
  await page
    .getByRole("link", { name: fixtures.published.at(-1).title })
    .click();
  await expect(
    page.getByRole("heading", { name: fixtures.published.at(-1).title }),
  ).toBeVisible();
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
  ).toBe(true);
});

test("authenticated frontend props do not inherit host auth or control panel data", async ({
  page,
}) => {
  await login(page);
  await page.goto("/inertia-poc");
  await expect(
    page.getByRole("heading", { name: "Entries", exact: true }),
  ).toBeVisible();
  const initial = await page.locator("script[data-page=app]").textContent();
  const props = JSON.parse(initial!).props;
  expect(Object.keys(props).sort()).toEqual([
    "entries",
    "indexUrl",
    "section",
    "site",
  ]);
  expect(initial).not.toContain(credentials.email);
  const scripts = await page
    .locator("script[src]")
    .evaluateAll((nodes) => nodes.map((node) => node.getAttribute("src")));
  expect(
    scripts.some((src) =>
      src?.includes("/vendor/robuust/craft-inertia-poc/build/"),
    ),
  ).toBe(true);
  expect(scripts.some((src) => src?.includes("/vendor/craft/build/"))).toBe(
    false,
  );
  await page.goto("/admin/dashboard");
  await expect(
    page.getByRole("heading", { name: "Dashboard", exact: true }),
  ).toBeVisible();
});

test("host SSR keeps working while plugin pages use their own client bundle", async ({
  page,
}) => {
  const hostResponse = await page.goto("/login");
  expect(hostResponse?.status()).toBe(200);
  await expect(page.locator("#app")).toHaveAttribute(
    "data-server-rendered",
    "true",
  );
  const pluginResponse = await page.goto("/inertia-poc");
  expect(pluginResponse?.status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "Entries", exact: true }),
  ).toBeVisible();
  expect(
    await page.locator("#app").getAttribute("data-server-rendered"),
  ).not.toBe("true");
  await page.goto("/login");
  await expect(page.locator("#app")).toHaveAttribute(
    "data-server-rendered",
    "true",
  );
});

test("Craft editor opens the plugin preview target for a named draft", async ({
  page,
}) => {
  const fixture = fixtureCommand("create-publish-case");
  await login(page);
  await page.goto(fixture.draftEditUrl);
  await expect(page.locator("input[name=title]")).toHaveValue(fixture.after);
  const popupPromise = page.waitForEvent("popup");
  await page
    .getByRole("link", { name: /^Inertia \+ Vue proof of concept/ })
    .click();
  const preview = await popupPromise;
  await expect(
    preview.getByRole("heading", { name: fixture.after }),
  ).toBeVisible();
  await expect(preview.getByRole("status")).toContainText("Live Preview");
  await expect(preview.locator(".entry-body")).toHaveText(
    "Published from named draft",
  );
  await preview.close();
  await page.goto(entryUrl(fixture.id));
  await expect(
    page.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
});

test("Live Preview automatically refreshes draft title, summary and body without publishing", async ({
  page,
  browser,
}) => {
  test.setTimeout(90_000);
  const fixture = fixtureCommand("create-preview-case");
  await login(page);
  const idSlug = new URL(fixture.editUrl).pathname.split("/").at(-1);
  const response = await page.goto(`/admin/preview/${idSlug}`);
  expect(response?.status()).toBe(200);
  await page.getByText("Primary entry page", { exact: true }).click();
  await page
    .getByText("Inertia + Vue proof of concept", { exact: true })
    .click();
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
  await expect(frame.locator(".entry-body")).toHaveText(
    "Live preview body change",
  );
  await expect(frame.getByRole("status")).toContainText("Live Preview");
  const publicContext = await browser.newContext();
  try {
    const publicPage = await publicContext.newPage();
    await publicPage.goto(`http://127.0.0.1:18766${entryUrl(fixture.id)}`);
    await expect(
      publicPage.getByRole("heading", { name: fixture.before }),
    ).toBeVisible();
    await expect(publicPage.locator("main")).not.toContainText(
      "Live preview body change",
    );
  } finally {
    await publicContext.close();
  }
});

test("publishing a named draft updates the public entry", async ({ page }) => {
  const fixture = fixtureCommand("create-publish-case");
  await page.goto(entryUrl(fixture.id));
  await expect(
    page.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
  fixtureCommand("publish", String(fixture.draftId));
  await page.reload();
  await expect(
    page.getByRole("heading", { name: fixture.after }),
  ).toBeVisible();
  await expect(page.locator(".entry-body")).toHaveText(
    "Published from named draft",
  );
  await expect(page.getByRole("status")).toHaveCount(0);
});
