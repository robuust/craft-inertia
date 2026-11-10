import { test, expect } from "@playwright/test";
import type { APIResponse, Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const app = `${runtime}/playground`;
const configPath = `${app}/config/site-content.php`;
const fixtures = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
const messages = new WeakMap<Page, string[]>();
let originalConfig: string;
function fixture(mode: string): any {
  const result = execFileSync(
    "php",
    [fileURLToPath(new URL("./fixtures.php", import.meta.url)), mode],
    { cwd: app, encoding: "utf8" },
  );
  return JSON.parse(result.split("POC_FIXTURES_JSON=").at(-1)!);
}
const headers = () => ({
  "X-Inertia": "true",
  "X-Inertia-Version": fixture("asset-version").version,
});
function enable(enabled = true, indexing = false) {
  expect(originalConfig).toMatch(/'cacheEnabled'\s*=>\s*false/);
  const next = originalConfig.replace(
    /'cacheEnabled'\s*=>\s*false/,
    `'cacheEnabled' => ${enabled}`,
  );
  writeFileSync(
    configPath,
    indexing
      ? next.replace(
          /'indexableSites'\s*=>\s*(?:\[\]|array\s*\(\s*\))/,
          "'indexableSites' => ['default']",
        )
      : next,
  );
}
function cache(response: APIResponse): { hits: string[]; writes: string[] } {
  expect(response.headers()["cache-control"]).toContain("private");
  expect(response.headers()["cache-control"]).toContain("no-store");
  expect(response.headers().vary).toContain("X-Inertia");
  return JSON.parse(response.headers()["x-e2e-content-cache"]!);
}
test.beforeAll(() => {
  originalConfig = readFileSync(configPath, "utf8");
});
test.beforeEach(({ page }) => {
  enable();
  const errors: string[] = [];
  messages.set(page, errors);
  page.on("pageerror", (error) => errors.push(error.message));
  page.on("console", (message) => {
    if (/hydration|mismatch/i.test(message.text())) errors.push(message.text());
  });
});
test.afterEach(async ({ page }, info) => {
  writeFileSync(configPath, originalConfig);
  await info.attach("browser-errors-and-hydration", {
    body: JSON.stringify(messages.get(page)),
    contentType: "application/json",
  });
  expect(messages.get(page)).toEqual([]);
});

test("default bypasses data caching and opt-in shares HTML/JSON data with fresh indexing policy", async ({
  request,
  page,
}) => {
  const entry = fixture("cache-create");
  fixture("cache-publish");
  try {
    enable(false);
    expect(cache(await request.get(entry.url, { headers: headers() }))).toEqual(
      { hits: [], writes: [] },
    );
    enable();
    const html = await request.get(entry.url);
    const first = cache(html);
    expect(first.writes).toHaveLength(1);
    expect(await html.text()).toContain('data-server-rendered="true"');
    enable(true, true);
    const json = await request.get(entry.url, {
      headers: headers(),
    });
    expect(cache(json)).toEqual({ hits: first.writes, writes: [] });
    expect(json.headers()["x-robots-tag"]).toBe("index, follow");
    expect((await json.json()).props.seo.robots).toBe("index, follow");
    await page.goto(entry.url);
    await expect(
      page.getByRole("heading", {
        name: entry.title,
        exact: true,
      }),
    ).toBeVisible();
    await page
      .getByRole("link", { name: "Back to entries", exact: true })
      .click();
    await expect(page).toHaveURL(/\/articles$/);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
      "content",
      "index, follow",
    );
  } finally {
    fixture("cache-delete");
  }
});

test("filters sorting pages and normalized inputs keep separate public slices", async ({
  request,
}) => {
  const keys: string[] = [];
  for (const [query, count, title] of [
    ["q=E2E%20article&sort=oldest&page=1", 20, "E2E article 01"],
    ["q=E2E%20article&sort=oldest&page=2", 2, "E2E article 21"],
    ["topic=architecture&sort=title", 2, "E2E article 21"],
    ["topic=missing", 0, undefined],
  ] as const) {
    const response = await request.get(`/articles?${query}`, {
      headers: headers(),
    });
    const observed = cache(response);
    expect(observed.writes).toHaveLength(1);
    keys.push(observed.writes[0]!);
    const { props } = await response.json();
    expect(props.entries).toHaveLength(count);
    expect(props.entries[0]?.title).toBe(title);
    const again = await request.get(`/articles?${query}`, {
      headers: headers(),
    });
    expect(cache(again)).toEqual({ hits: observed.writes, writes: [] });
  }
  expect(new Set(keys).size).toBe(4);
  const normalized = await request.get(
    "/articles?q=%20E2E%20article%20&sort=oldest&page=2",
    { headers: headers() },
  );
  expect(cache(normalized)).toEqual({ hits: [keys[1]], writes: [] });
  expect((await normalized.json()).props.pagination.prevUrl).toContain(
    "page=1",
  );
});

test("native publish save unpublish and delete invalidate warmed data including an empty query", async ({
  request,
  page,
}) => {
  const entry = fixture("cache-create");
  const overview = "/articles?q=CacheRuntimePublication";
  const empty = await request.get(overview, { headers: headers() });
  expect((await empty.json()).props.entries).toEqual([]);
  expect(cache(empty).writes).toHaveLength(1);
  expect(
    cache(await request.get(overview, { headers: headers() })).writes,
  ).toEqual([]);
  fixture("cache-publish");
  const published = await request.get(overview, { headers: headers() });
  expect(cache(published).writes).toHaveLength(1);
  expect((await published.json()).props.entries[0].title).toBe(entry.title);
  const detail = await request.get(entry.url, { headers: headers() });
  expect(cache(detail).writes).toHaveLength(1);
  expect(
    cache(await request.get(entry.url, { headers: headers() })).writes,
  ).toEqual([]);
  fixture("cache-update");
  const updated = await request.get(entry.url, { headers: headers() });
  expect(cache(updated).writes).toHaveLength(1);
  expect((await updated.json()).props.entry.body).toBe(
    "Updated through the native Craft save API.",
  );
  await page.goto(entry.url);
  await expect(
    page.getByRole("heading", {
      name: "CacheRuntimePublication after native save",
      exact: true,
    }),
  ).toBeVisible();
  fixture("cache-unpublish");
  expect(
    (await request.get(overview, { headers: headers() }).then((r) => r.json()))
      .props.entries,
  ).toEqual([]);
  expect((await request.get(entry.url, { headers: headers() })).status()).toBe(
    404,
  );
  fixture("cache-publish");
  expect((await request.get(entry.url, { headers: headers() })).status()).toBe(
    200,
  );
  fixture("cache-delete");
  expect((await request.get(entry.url, { headers: headers() })).status()).toBe(
    404,
  );
  expect(
    (await request.get(overview, { headers: headers() }).then((r) => r.json()))
      .props.entries,
  ).toEqual([]);
});

test("native current site isolates the same canonical entry and its overview", async ({
  request,
}) => {
  const sites = JSON.parse(readFileSync(`${runtime}/sites.json`, "utf8"));
  const keys: string[] = [];
  for (const site of [
    { article: sites.primaryArticle },
    ...Object.values(sites.sites),
  ] as any[]) {
    const url = new URL(site.article.url);
    const host = url.host;
    url.hostname = "127.0.0.1";
    const first = await request.get(url.href, {
      headers: { ...headers(), Host: host },
    });
    expect((await first.json()).props.entry.title).toBe(site.article.title);
    const observed = cache(first);
    const key = observed.writes[0] ?? observed.hits[0];
    expect(key).toBeTruthy();
    keys.push(key!);
    const again = await request.get(url.href, {
      headers: { ...headers(), Host: host },
    });
    expect(cache(again)).toEqual({ hits: [key], writes: [] });
    const index = new URL((await again.json()).props.indexUrl);
    index.hostname = "127.0.0.1";
    index.search = "?q=E2E&sort=oldest&page=2";
    const overview = await request.get(index.href, {
      headers: { ...headers(), Host: host },
    });
    const overviewKey = cache(overview);
    keys.push((overviewKey.writes[0] ?? overviewKey.hits[0])!);
  }
  expect(new Set(keys).size).toBe(keys.length);
});

test("warm data remains inaccessible to preview token site-token and consumed flash requests", async ({
  request,
  page,
}) => {
  const sensitive = fixture("indexing-case");
  await request.get("/articles", { headers: headers() });
  for (const path of [
    `/articles?token=${sensitive.routeToken}`,
    `/articles?siteToken=${encodeURIComponent(sensitive.siteToken)}`,
    "/articles?x-craft-preview=1",
    `${fixtures.unpublished.url}?token=${fixtures.unpublished.token}`,
  ]) {
    const response = await request.get(path, { headers: headers() });
    expect(response.status()).toBe(200);
    expect(cache(response)).toEqual({ hits: [], writes: [] });
    expect((await response.json()).props.seo.structuredData).toBeNull();
  }
  const flash = await page.goto("/e2e/rendering/flash");
  expect(JSON.parse(flash!.headers()["x-e2e-content-cache"]!)).toEqual({
    hits: [],
    writes: [],
  });
  await expect(
    page.locator('head script[type="application/ld+json"]'),
  ).toHaveCount(0);
});

test("signed-in requests never read or populate public content data", async ({
  request,
  page,
}) => {
  await request.get("/articles", { headers: headers() });
  const credentials = JSON.parse(
    readFileSync(`${runtime}/credentials.json`, "utf8"),
  );
  await page.goto("/admin/login");
  await page.locator("input[name=username]").fill(credentials.username);
  await page.locator("input[name=password]").fill(credentials.password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page).not.toHaveURL(/\/admin\/login/);
  const response = await page.request.get("/articles", { headers: headers() });
  expect(cache(response)).toEqual({ hits: [], writes: [] });
  expect((await response.json()).props.seo.structuredData).toBeNull();
});
