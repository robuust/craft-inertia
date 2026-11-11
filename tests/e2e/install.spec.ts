import { expect, test } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const application = fileURLToPath(
  new URL("./.runtime/installation", import.meta.url),
);
function artisan(args: string[], environment: NodeJS.ProcessEnv = {}) {
  return execFileSync("php", ["artisan", ...args, "--no-interaction"], {
    cwd: application,
    env: { ...process.env, ...environment },
    encoding: "utf8",
  });
}
function optIn(enabled: boolean) {
  artisan(["config:clear"]);
  artisan(["config:cache"], { FRONTEND_ENABLED: String(enabled) });
}

test.describe.configure({ mode: "serial" });
test.afterAll(() => {
  artisan(["craft:plugin:enable", "inertia"]);
  artisan(["route:clear"]);
  artisan(["config:clear"]);
});

test("installing alone preserves project native pages", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { name: "Welcome" })).toBeVisible();
  await expect(page.locator("[data-native]")).toBeVisible();
  await expect(page.locator("#app")).toHaveCount(0);
  optIn(true);
});

test("project SSR renders content without JavaScript", async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    await page.goto("http://127.0.0.1:18777/about");
    await expect(
      page.getByRole("heading", { name: "About this site" }),
    ).toBeVisible();
    await expect(page).toHaveTitle("About this site");
    await expect(page.locator("#app")).toBeVisible();
  } finally {
    await context.close();
  }
});

test("project Vue pages navigate through the Inertia protocol", async ({
  page,
}) => {
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  await page.goto("/");
  await expect(page.getByRole("heading", { name: "Welcome" })).toBeVisible();
  await page.evaluate(() => {
    (
      window as Window & { installationDocument?: string }
    ).installationDocument = "same-document";
  });
  const response = page.waitForResponse(
    (item) =>
      item.url().endsWith("/about") &&
      item.request().headers()["x-inertia"] === "true",
  );
  await page.getByRole("link", { name: "About", exact: true }).click();
  const navigation = await response;
  expect(navigation.headers()["x-inertia"]).toBe("true");
  expect((await navigation.json()).component).toBe("Page");
  await expect(
    page.getByRole("heading", { name: "About this site" }),
  ).toBeVisible();
  expect(
    await page.evaluate(
      () =>
        (window as Window & { installationDocument?: string })
          .installationDocument,
    ),
  ).toBe("same-document");
  expect(errors).toEqual([]);
});

test("native disable and enable work with cached routes", async ({
  page,
  request,
}) => {
  artisan(["route:cache"]);
  try {
    artisan(["craft:plugin:disable", "inertia"]);
    await page.goto("/about");
    await expect(page.locator("[data-native]")).toBeVisible();
    await expect(
      page.getByRole("heading", { name: "About this site" }),
    ).toBeVisible();
    await expect(page.locator("#app")).toHaveCount(0);
    const action = await request.get("/actions/users/session-info", {
      headers: { "X-Inertia": "true", Accept: "application/json" },
    });
    expect(action.status()).toBe(200);
    expect((await action.json()).isGuest).toBe(true);
    expect(action.headers()["x-inertia"]).toBeUndefined();
    const api = await request.get("/api/ping", {
      headers: { "X-Inertia": "true" },
    });
    expect(await api.json()).toEqual({ native: true });
    const feed = await request.get("/feed");
    expect(await feed.text()).toBe("<feed>Native feed</feed>");
    const cp = await request.get("/admin/login", {
      headers: { Accept: "text/html" },
    });
    expect(cp.status()).toBe(200);
    expect((await cp.text()).replaceAll("\\/", "/")).toContain(
      '"component":"auth/Login"',
    );
    const missing = await request.get("/not-configured");
    expect(missing.status()).toBe(404);
    expect(missing.headers()["x-inertia"]).toBeUndefined();
  } finally {
    artisan(["craft:plugin:enable", "inertia"]);
  }
  await page.goto("/about");
  await expect(page.locator("#app")).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "About this site" }),
  ).toBeVisible();
});

test("project opt-out restores native views while plugin stays enabled", async ({
  page,
  request,
}) => {
  optIn(false);
  try {
    const response = await request.get("/about", {
      headers: { "X-Inertia": "true" },
    });
    expect(response.status()).toBe(200);
    expect(response.headers()["x-inertia"]).toBeUndefined();
    expect(await response.text()).toContain("Project native fallback");
    await page.goto("/");
    await expect(page.locator("[data-native]")).toBeVisible();
    await expect(page.locator("#app")).toHaveCount(0);
  } finally {
    optIn(true);
  }
});
