import { test, expect } from "@playwright/test";
import type { Page, APIResponse } from "@playwright/test";
import { readFileSync, writeFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const fixtures = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
function version(): string {
  const result = execFileSync(
    "php",
    [
      fileURLToPath(new URL("./fixtures.php", import.meta.url)),
      "asset-version",
    ],
    { cwd: `${runtime}/playground`, encoding: "utf8" },
  );
  return JSON.parse(result.split("POC_FIXTURES_JSON=").at(-1)!).version;
}
const headers = () => ({ "X-Inertia": "true", "X-Inertia-Version": version() });
function privateResponse(response: APIResponse) {
  expect(response.headers()["cache-control"]).toContain("private");
  expect(response.headers()["cache-control"]).toContain("no-store");
  expect(response.headers().vary).toContain("X-Inertia");
}
const errors = new WeakMap<Page, string[]>();
test.beforeEach(({ page }) => {
  const messages: string[] = [];
  errors.set(page, messages);
  page.on("pageerror", (error) => messages.push(error.message));
  page.on("console", (message) => {
    if (/hydration|mismatch/i.test(message.text()))
      messages.push(message.text());
  });
});
test.afterEach(async ({ page }, info) => {
  await info.attach("browser-errors-and-hydration", {
    body: JSON.stringify(errors.get(page)),
    contentType: "application/json",
  });
  expect(errors.get(page)).toEqual([]);
});

test("guest HTML contains rendered content and metadata before JavaScript", async ({
  browser,
  request,
}) => {
  const entry = fixtures.published[0];
  const response = await request.get(entry.url);
  privateResponse(response);
  const html = await response.text();
  expect(html).toContain('data-server-rendered="true"');
  const context = await browser.newContext({ javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    await page.goto(entry.url);
    await expect(
      page.getByRole("heading", { name: entry.title }),
    ).toBeVisible();
    await expect(page.locator("title")).toHaveCount(1);
    await expect(page.locator("link[rel=canonical]")).toHaveAttribute(
      "href",
      entry.url,
    );
    await expect(page.locator("meta[name=description]")).toHaveAttribute(
      "content",
      /.+/,
    );
  } finally {
    await context.close();
  }
});

test("hydration and Inertia visits replace head state without duplicate content", async ({
  page,
}) => {
  await page.goto(fixtures.published[0].url);
  await expect(page.locator("title")).toHaveCount(1);
  await expect(page).toHaveTitle(
    `${fixtures.published[0].title} - Craft 6 Playground`,
  );
  await page
    .getByRole("link", { name: "Back to entries", exact: true })
    .click();
  await expect(page).toHaveTitle("Articles - Craft 6 Playground");
  await expect(page.locator("link[rel=canonical]")).toHaveCount(1);
  await expect(page.locator("link[rel=canonical]")).toHaveAttribute(
    "href",
    "http://127.0.0.1:18766/articles",
  );
  await expect(page.locator("h1")).toHaveCount(1);
  await expect(page.locator("meta[name=description]")).toHaveCount(1);
});

test("native site variants have separate SSR content, canonical and alternate links", async ({
  page,
  request,
}) => {
  const sites = JSON.parse(readFileSync(`${runtime}/sites.json`, "utf8"));
  for (const site of Object.values(sites.sites) as any[]) {
    const url = new URL(site.article.url);
    const loopback = new URL(url);
    loopback.hostname = "127.0.0.1";
    const response = await request.get(loopback.href, {
      headers: { Host: url.host },
    });
    privateResponse(response);
    expect(await response.text()).toContain('data-server-rendered="true"');
    await page.goto(url.href);
    await expect(
      page.getByRole("heading", { name: site.article.title }),
    ).toBeVisible();
    await expect(page.locator("link[rel=canonical]")).toHaveAttribute(
      "href",
      site.article.url,
    );
    const alternates = await page
      .locator("link[rel=alternate]")
      .evaluateAll((links) => links.map((link) => link.getAttribute("href")));
    expect(alternates).toContain(site.article.url);
    expect(alternates).toContain(sites.primaryArticle.url);
    const json = await request.get(loopback.href, {
      headers: { ...headers(), Host: url.host },
    });
    privateResponse(json);
    expect((await json.json()).props.entry.title).toBe(site.article.title);
  }
});

for (const code of [404, 500, 503]) {
  test(`public ${code} keeps status for HTML, JSON and client rendering`, async ({
    page,
    request,
  }) => {
    const url = `/e2e/rendering/error/${code}`;
    const html = await request.get(url);
    privateResponse(html);
    expect(html.status()).toBe(code);
    expect(await html.text()).not.toContain("Synthetic exception detail");
    const json = await request.get(url, { headers: headers() });
    privateResponse(json);
    expect(json.status()).toBe(code);
    expect((await json.json()).component).toBe("Site/Error");
    if (code === 503) expect(json.headers()["retry-after"]).toBe("120");
    const response = await page.goto(url);
    expect(response?.status()).toBe(code);
    await expect(
      page.getByRole("heading", { name: String(code), exact: true }),
    ).toBeVisible();
  });
}

test("an Inertia visit presents the project error page and removes content metadata", async ({
  page,
}) => {
  await page.goto("/e2e/rendering/page");
  const response = page.waitForResponse(
    (result) =>
      result.url().includes("/error/404") &&
      result.request().headers()["x-inertia"] === "true",
  );
  await page.getByRole("link", { name: "Articles", exact: true }).click();
  expect((await response).status()).toBe(404);
  await expect(
    page.getByRole("heading", { name: "404", exact: true }),
  ).toBeVisible();
  await expect(page.locator("link[rel=canonical]")).toHaveCount(0);
});

test("real isolated maintenance mode keeps the public 503 and Retry-After", async ({
  page,
  request,
}) => {
  const artisan = (...args: string[]) =>
    execFileSync("php", ["artisan", ...args, "--no-interaction"], {
      cwd: `${runtime}/playground`,
      encoding: "utf8",
    });
  const environmentPath = `${runtime}/playground/.env`;
  const originalEnvironment = readFileSync(environmentPath, "utf8");
  writeFileSync(
    environmentPath,
    originalEnvironment.replace(/^APP_DEBUG=.*$/m, 'APP_DEBUG="false"'),
  );
  artisan("down", "--retry=120");
  try {
    for (const extra of [{}, headers()]) {
      const response = await request.get("/articles", { headers: extra });
      expect(response.status()).toBe(503);
      privateResponse(response);
      expect(response.headers()["retry-after"]).toBe("120");
    }
    await page.goto("/articles");
    await expect(
      page.getByText(
        "The site is temporarily unavailable. Please try again later.",
      ),
    ).toBeVisible();
  } finally {
    artisan("up");
    writeFileSync(environmentPath, originalEnvironment);
  }
});

test("redirects and native JSON, action, asset and debug errors keep their contracts", async ({
  request,
}) => {
  for (const extra of [{}, headers()]) {
    const response = await request.get("/e2e/rendering/redirect", {
      headers: extra,
      maxRedirects: 0,
    });
    expect(response.status()).toBe(307);
    expect(response.headers().location).toContain("/articles");
  }
  for (const url of [
    "/actions/missing",
    "/build/missing.js",
    "/e2e/rendering/error/404",
  ]) {
    const response = await request.get(url, {
      headers: { Accept: "application/json" },
    });
    expect(response.status()).toBe(404);
    expect(response.headers()["x-inertia"]).toBeUndefined();
    expect(await response.text()).not.toContain('"component"');
  }
  const debug = await request.get("/e2e/rendering/error/500?debug=1");
  expect(debug.status()).toBe(500);
  expect(await debug.text()).not.toContain('data-page="app"');
});

test("an SSR outage falls back to private client rendering", async ({
  page,
  request,
}) => {
  const response = await request.get("/e2e/rendering/page?fallback=1");
  privateResponse(response);
  expect(response.status()).toBe(200);
  expect(await response.text()).not.toContain('data-server-rendered="true"');
  await page.goto("/e2e/rendering/page?fallback=1");
  await expect(
    page.getByRole("heading", { name: "Public rendering fixture" }),
  ).toBeVisible();
});

test("a changed Vite manifest negotiates 409 then a full document reload", async ({
  page,
  request,
}) => {
  const manifest = `${runtime}/playground/public/build/manifest.json`;
  const original = readFileSync(manifest, "utf8");
  const previous = version();
  await page.goto("/");
  try {
    writeFileSync(manifest, `${original}\n`);
    expect(version()).not.toBe(previous);
    const conflict = await request.get("/articles", {
      headers: { "X-Inertia": "true", "X-Inertia-Version": previous },
    });
    expect(conflict.status()).toBe(409);
    privateResponse(conflict);
    expect(conflict.headers()["x-inertia-location"]).toBe(
      "http://127.0.0.1:18766/articles",
    );
    const document = page.waitForRequest(
      (result) =>
        result.isNavigationRequest() && result.url().endsWith("/articles"),
    );
    await page.getByRole("link", { name: "Articles", exact: true }).click();
    expect((await document).headers()["x-inertia"]).toBeUndefined();
    await expect(
      page.getByRole("heading", { name: "Articles", exact: true }),
    ).toBeVisible();
  } finally {
    writeFileSync(manifest, original);
  }
});

test("authorized preview and signed-in pages stay private and outside public SSR", async ({
  page,
  request,
}) => {
  const preview = `${fixtures.unpublished.url}?token=${encodeURIComponent(fixtures.unpublished.token)}`;
  const previewHtml = await request.get(preview);
  expect(previewHtml.status()).toBe(200);
  privateResponse(previewHtml);
  expect(await previewHtml.text()).not.toContain('data-server-rendered="true"');
  const previewJson = await request.get(preview, { headers: headers() });
  privateResponse(previewJson);
  expect((await previewJson.json()).props.isPreview).toBe(true);
  const publicVisit = await request.get(fixtures.unpublished.url);
  expect(publicVisit.status()).toBe(404);
  expect(await publicVisit.text()).not.toContain(
    "E2E unpublished draft secret",
  );

  const credentials = JSON.parse(
    readFileSync(`${runtime}/credentials.json`, "utf8"),
  );
  await page.goto("/admin/login");
  await page.locator("input[name=username]").fill(credentials.username);
  await page.locator("input[name=password]").fill(credentials.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).not.toHaveURL(/\/admin\/login/);
  for (const extra of [{}, headers()]) {
    const response = await page.request.get("/articles", { headers: extra });
    privateResponse(response);
    expect(response.status()).toBe(200);
    expect(await response.text()).not.toContain('data-server-rendered="true"');
  }
  const guest = await request.get("/articles");
  privateResponse(guest);
  expect(await guest.text()).toContain('data-server-rendered="true"');
});
