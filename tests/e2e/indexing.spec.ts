import { test, expect } from "@playwright/test";
import type { APIResponse, Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const app = `${runtime}/playground`;
const fixtures = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
const contentPath = `${app}/config/site-content.php`;
const generalPath = `${app}/config/craft/general.php`;
let originalContent: string;
let originalGeneral: string;
let malicious: any;
const messages = new WeakMap<Page, string[]>();
function fixture(mode: string): any {
  const output = execFileSync(
    "php",
    [fileURLToPath(new URL("./fixtures.php", import.meta.url)), mode],
    { cwd: app, encoding: "utf8" },
  );
  return JSON.parse(output.split("POC_FIXTURES_JSON=").at(-1)!);
}
const headers = () => ({
  "X-Inertia": "true",
  "X-Inertia-Version": fixture("asset-version").version,
});
function optIn(enabled = true) {
  const pattern = /'indexableSites'\s*=>\s*(?:\[\]|array\s*\(\s*\))/;
  expect(originalContent).toMatch(pattern);
  writeFileSync(
    contentPath,
    originalContent.replace(
      pattern,
      `'indexableSites' => ${enabled ? "['default']" : "[]"}`,
    ),
  );
}
function privateResponse(response: APIResponse) {
  expect(response.headers()["cache-control"]).toContain("no-store");
  expect(response.headers()["cache-control"]).toContain("private");
  expect(response.headers().vary).toContain("X-Inertia");
}
async function schema(page: Page) {
  const script = page.locator('head script[type="application/ld+json"]');
  await expect(script).toHaveCount(1);
  return JSON.parse((await script.textContent())!);
}
test.beforeAll(() => {
  originalContent = readFileSync(contentPath, "utf8");
  originalGeneral = readFileSync(generalPath, "utf8");
  malicious = fixture("indexing-case");
});
test.beforeEach(({ page }) => {
  optIn();
  const errors: string[] = [];
  messages.set(page, errors);
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("console", (message) => {
    if (/hydration|mismatch/i.test(message.text())) errors.push(message.text());
  });
});
test.afterEach(async ({ page }, info) => {
  writeFileSync(contentPath, originalContent);
  writeFileSync(generalPath, originalGeneral);
  await info.attach("browser-errors-and-hydration", {
    body: JSON.stringify(messages.get(page)),
    contentType: "application/json",
  });
  expect(messages.get(page)).toEqual([]);
});

test("default denies indexing while opt-in HTML and Inertia metadata agree", async ({
  page,
  request,
}) => {
  optIn(false);
  const denied = await request.get("/articles", { headers: headers() });
  expect(denied.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  expect((await denied.json()).props.seo.robots).toBe("noindex, nofollow");
  optIn();
  for (const extra of [{}, headers()]) {
    const response = await request.get("/articles?q=published", {
      headers: extra,
    });
    expect(response.status()).toBe(200);
    expect(response.headers()["x-robots-tag"]).toBe("index, follow");
    privateResponse(response);
  }
  await page.goto("/articles");
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "index, follow",
  );
  expect((await schema(page)).url).toBe("http://127.0.0.1:18766/articles");
});

test("safe JSON-LD appears in SSR HTML and survives hydration with hostile content", async ({
  browser,
  page,
  request,
}) => {
  const response = await request.get(malicious.url);
  const html = await response.text();
  expect(html).toContain('data-server-rendered="true"');
  expect(html).toContain("\\u003C");
  const noJs = await browser.newContext({ javaScriptEnabled: false });
  try {
    const initial = await noJs.newPage();
    await initial.goto(malicious.url);
    expect(await schema(initial)).toMatchObject({
      "@type": "WebPage",
      name: malicious.payload,
      description: malicious.payload,
      url: malicious.url,
    });
    await expect(
      initial.locator(
        'head script:not([type="module"]):not([type="application/ld+json"])',
      ),
    ).toHaveCount(0);
  } finally {
    await noJs.close();
  }
  await page.goto(malicious.url);
  await expect(
    page.getByRole("heading", { name: malicious.payload, exact: true }),
  ).toBeVisible();
  expect((await schema(page)).name).toBe(malicious.payload);
  expect(
    await page.evaluate(() => (window as any).compromised),
  ).toBeUndefined();
  await expect(page.locator("head img")).toHaveCount(0);
});

test("Inertia visits and Back replace and deduplicate structured data", async ({
  page,
}) => {
  await page.goto(fixtures.published[0].url);
  expect((await schema(page)).name).toBe(fixtures.published[0].title);
  await page
    .getByRole("link", { name: "Back to entries", exact: true })
    .click();
  await expect(page).toHaveURL(/\/articles$/);
  expect((await schema(page)).name).toBe("Articles");
  await expect(page.locator("meta[name=robots]")).toHaveCount(1);
  await page.goBack();
  await expect(page).toHaveURL(fixtures.published[0].url);
  expect((await schema(page)).name).toBe(fixtures.published[0].title);
  await page.goForward();
  expect((await schema(page)).name).toBe("Articles");
});

test("preview, consumed native token and site-token contexts stay private", async ({
  page,
  request,
}) => {
  for (const url of [
    `/articles?token=${malicious.routeToken}`,
    `/articles?siteToken=${encodeURIComponent(malicious.siteToken)}`,
    `${fixtures.unpublished.url}?token=${fixtures.unpublished.token}`,
    "/articles?x-craft-preview=1",
  ]) {
    const response = await request.get(url, { headers: headers() });
    expect(response.status()).toBe(200);
    privateResponse(response);
    expect(["none", "noindex, nofollow"]).toContain(
      response.headers()["x-robots-tag"],
    );
    const seo = (await response.json()).props.seo;
    expect(seo.robots).toBe("noindex, nofollow");
    expect(seo.structuredData).toBeNull();
  }
  await page.goto(fixtures.published[0].url);
  optIn(false);
  await page
    .getByRole("link", { name: "Back to entries", exact: true })
    .click();
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
});

test("native disallowRobots wins over opt-in in both representations", async ({
  page,
  request,
}) => {
  writeFileSync(
    generalPath,
    originalGeneral.replace(
      "GeneralConfig::create()",
      "GeneralConfig::create()->disallowRobots(true)",
    ),
  );
  for (const extra of [{}, headers()]) {
    const response = await request.get("/articles", { headers: extra });
    expect(["none", "noindex, nofollow"]).toContain(
      response.headers()["x-robots-tag"],
    );
    privateResponse(response);
  }
  await page.goto("/articles");
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
});

test("authenticated content omits structured data and indexing", async ({
  page,
}) => {
  const credentials = JSON.parse(
    readFileSync(`${runtime}/credentials.json`, "utf8"),
  );
  await page.goto("/admin/login");
  await page.locator("input[name=username]").fill(credentials.username);
  await page.locator("input[name=password]").fill(credentials.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).not.toHaveURL(/\/admin\/login/);
  const response = await page.request.get("/articles", { headers: headers() });
  expect(response.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  expect((await response.json()).props.seo.structuredData).toBeNull();
  await page.goto("/articles");
  await expect(
    page.locator('head script[type="application/ld+json"]'),
  ).toHaveCount(0);
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
});

test("Inertia error visits remove structured data and Back restores it", async ({
  page,
}) => {
  await page.goto("/e2e/rendering/indexing-page");
  expect((await schema(page)).name).toBe("Articles");
  await page.getByRole("link", { name: "Articles", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "404", exact: true }),
  ).toBeVisible();
  await expect(
    page.locator('head script[type="application/ld+json"]'),
  ).toHaveCount(0);
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
  await page.goBack();
  expect((await schema(page)).name).toBe("Articles");
});

test("consumed one-time flash remains outside SSR, structured data and indexing", async ({
  page,
}) => {
  const response = await page.goto("/e2e/rendering/flash");
  expect(response?.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  expect(await response?.text()).not.toContain('data-server-rendered="true"');
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
  await expect(
    page.locator('head script[type="application/ld+json"]'),
  ).toHaveCount(0);
  await page.getByRole("link", { name: "Home", exact: true }).click();
  await expect(page.locator("meta[name=robots]")).toHaveAttribute(
    "content",
    "index, follow",
  );
  expect((await schema(page))["@type"]).toBe("WebPage");
});

test("site allowlist follows native site identity", async ({ request }) => {
  const sites = JSON.parse(readFileSync(`${runtime}/sites.json`, "utf8"));
  for (const site of Object.values(sites.sites) as any[]) {
    const url = new URL(site.article.url);
    const loopback = new URL(url);
    loopback.hostname = "127.0.0.1";
    const response = await request.get(loopback.href, {
      headers: { ...headers(), Host: url.host },
    });
    expect(response.status()).toBe(200);
    const seo = (await response.json()).props.seo;
    expect(seo.robots).toBe("noindex, nofollow");
    expect(JSON.parse(seo.structuredData).url).toBe(site.article.url);
  }
});
