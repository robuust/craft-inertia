import { test, expect } from "@playwright/test";
import type { APIRequestContext, APIResponse, Page } from "@playwright/test";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
let fixture: any;
test.beforeAll(() => {
  test.setTimeout(120_000);
  if (!existsSync(`${runtime}/sites.json`)) {
    execFileSync(
      "php",
      [fileURLToPath(new URL("./fixtures.php", import.meta.url)), "seed-sites"],
      {
        cwd: `${runtime}/playground`,
        encoding: "utf8",
        timeout: 120_000,
      },
    );
  }
  fixture = JSON.parse(readFileSync(`${runtime}/sites.json`, "utf8"));
});
const original = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);
const output = execFileSync(
  "php",
  [fileURLToPath(new URL("./fixtures.php", import.meta.url)), "asset-version"],
  { cwd: `${runtime}/playground`, encoding: "utf8" },
);
const version = JSON.parse(output.split("POC_FIXTURES_JSON=").at(-1)!).version;
const inertiaHeaders = { "X-Inertia": "true", "X-Inertia-Version": version };

// Browser DNS is disposable launch configuration. HTTP checks use the same Host
// header over loopback, without changing the machine's DNS or hosts file.
async function get(request: APIRequestContext, url: string, inertia = true) {
  const parsed = new URL(url);
  const host = parsed.host;
  parsed.hostname = "127.0.0.1";
  return request.get(parsed.href, {
    headers: { ...(inertia ? inertiaHeaders : {}), Host: host },
  });
}
function expectPrivate(response: APIResponse) {
  expect(response.headers()["cache-control"]).toContain("private");
  expect(response.headers()["cache-control"]).toContain("no-store");
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
  expect.soft(errors, "No unhandled browser JavaScript errors").toEqual([]);
});

test("same-language sites retain distinct native identity and translated content", async ({
  request,
}) => {
  const identities = [];
  for (const site of Object.values(fixture.sites) as any[]) {
    const response = await get(request, site.article.url);
    expect(response.status()).toBe(200);
    expectPrivate(response);
    const { props } = await response.json();
    identities.push(props.site.id);
    expect(props.site).toMatchObject({
      id: site.id,
      uid: site.uid,
      handle: site.handle,
      language: "nl",
      baseUrl: site.baseUrl,
    });
    expect(props.entry.title).toBe(site.article.title);
    expect(props.entry.body).toBe(`${site.label} gepubliceerde inhoud`);
    expect(props.entry.richText).toContain(`${site.label} rijke tekst`);
    expect(props.entry.related[0].title).toBe(site.related.title);
    expect(props.entry.related[0].url).toBe(site.related.url);
    expect(props.entry.topics[0].title).toBe(site.topic.title);
    expect(props.entry.blocks[0].heading).toBe(`${site.label} block heading`);
    expect(props.entry.blocks[1].children[0].heading).toBe(
      `${site.label} nested heading`,
    );
    expect(props.globals.tagline).toBe(site.tagline);
    expect(props.indexUrl).toBe(`${site.baseUrl}artikelen`);
    expect(props.homeUrl).toBe(site.home.url);
    expect(props.isPreview).toBe(false);
  }
  expect(new Set(identities).size).toBe(2);
  const primary = await get(request, fixture.primaryArticle.url);
  const { props } = await primary.json();
  expect(props.entry.title).toBe(fixture.primaryArticle.title);
  expect(props.entry.blocks[0].heading).toBe("Article block heading");
  expect(props.globals.tagline).toBe(original.content.tagline);
});

for (const handle of ["dutch", "dutchDomain"]) {
  test(`${handle}: native homepage, overview, search and pagination keep site context`, async ({
    page,
    request,
  }) => {
    const site = fixture.sites[handle];
    const errors: string[] = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto(site.home.url);
    await expect(
      page.getByRole("heading", { name: site.home.title }),
    ).toBeVisible();
    await expect(page.locator("main")).toContainText(`${site.label} welkom`);
    await page.getByRole("link", { name: "Articles", exact: true }).click();
    await expect(page).toHaveURL(`${site.baseUrl}artikelen`);
    await page.getByRole("link", { name: "Next page", exact: true }).click();
    await expect(page).toHaveURL(/page=2/);
    expect(new URL(page.url()).pathname).toBe(
      new URL(`${site.baseUrl}artikelen`).pathname,
    );
    const response = await get(
      request,
      `${site.baseUrl}artikelen?q=${site.label}&topic=${site.topic.title === "" ? "" : `${site.label.toLowerCase()}-architectuur`}&sort=title`,
    );
    const { props } = await response.json();
    expect(props.site.id).toBe(site.id);
    expect(props.entries.map((entry: any) => entry.title)).toContain(
      site.article.title,
    );
    expect(
      props.entries.every((entry: any) =>
        entry.url.startsWith(`${site.baseUrl}artikelen/`),
      ),
    ).toBe(true);
    expect(props.filters).toMatchObject({
      q: site.label,
      topic: `${site.label.toLowerCase()}-architectuur`,
      sort: "title",
    });
    expect(errors).toEqual([]);
  });

  test(`${handle}: authorized draft uses prepared native context in HTML and JSON`, async ({
    page,
    request,
  }) => {
    const site = fixture.sites[handle];
    const url = `${site.article.url}?token=${site.draft.token}&x-craft-preview=forged-marker`;
    for (const inertia of [false, true]) {
      const response = await get(request, url, inertia);
      expect(response.status()).toBe(200);
      expectPrivate(response);
      if (inertia) {
        const data = await response.json();
        expect(data.props.isPreview).toBe(true);
        expect(data.props.entry.title).toBe(site.draft.title);
        expect(data.props.site.id).toBe(site.id);
        expect(JSON.parse(response.headers()["x-e2e-preview-markers"])).toEqual(
          {
            token: false,
            marker: true,
            requestIsPreview: false,
          },
        );
        expect(
          data.props.site.links.every(
            (link: any) => !link.url.includes("token="),
          ),
        ).toBe(true);
      }
    }
    await page.goto(url);
    await expect(
      page.getByRole("heading", { name: site.draft.title }),
    ).toBeVisible();
    await expect(page.getByRole("status")).toContainText("Live Preview");
    const publicResponse = await get(request, site.article.url);
    expect((await publicResponse.json()).props.entry.title).toBe(
      site.article.title,
    );
  });

  test(`${handle}: ordinary editor View launches native draft preview at its site URL`, async ({
    page,
    browser,
  }) => {
    const site = fixture.sites[handle];
    await login(page);
    await page.goto(site.draft.editUrl);
    await expect(page.locator("input[name=title]")).toHaveValue(
      site.draft.title,
    );
    const popupPromise = page.waitForEvent("popup");
    await page
      .getByRole("link", { name: /^View Opens in a new window/ })
      .click();
    const popup = await popupPromise;
    await expect(
      popup.getByRole("heading", { name: site.draft.title }),
    ).toBeVisible();
    expect(popup.url()).toContain(site.article.url);
    await expect(popup.getByRole("status")).toContainText("Live Preview");
    const visitor = await browser.newContext();
    try {
      const publicPage = await visitor.newPage();
      await publicPage.goto(site.article.url);
      await expect(
        publicPage.getByRole("heading", { name: site.article.title }),
      ).toBeVisible();
    } finally {
      await visitor.close();
    }
  });
}

test("translated site links perform full document navigation across origins", async ({
  page,
}) => {
  const site = fixture.sites.dutch;
  const other = fixture.sites.dutchDomain;
  await page.goto(site.article.url);
  const navigation = page.waitForRequest(
    (request) =>
      request.isNavigationRequest() && request.url() === other.article.url,
  );
  await page.getByRole("link", { name: other.name, exact: true }).click();
  const request = await navigation;
  expect(request.headers()["x-inertia"]).toBeUndefined();
  await expect(page).toHaveURL(other.article.url);
  await expect(
    page.getByRole("heading", { name: other.article.title }),
  ).toBeVisible();
  await page.getByRole("link", { name: site.name, exact: true }).click();
  await expect(page).toHaveURL(site.article.url);
});

test("native provisional and revision tokens authorize only their canonical content", async ({
  request,
  page,
}) => {
  for (const content of [fixture.provisional, fixture.revision]) {
    for (const inertia of [false, true]) {
      const response = await get(
        request,
        `${content.url}?token=${content.token}`,
        inertia,
      );
      expect(response.status()).toBe(200);
      expectPrivate(response);
      if (inertia) {
        const { props } = await response.json();
        expect(props.isPreview).toBe(true);
        expect(props.entry.title).toBe(content.secret);
      }
    }
    await page.goto(`${content.url}?token=${content.token}`);
    await expect(
      page.getByRole("heading", { name: content.secret }),
    ).toBeVisible();
    const response = await get(request, content.url);
    expect((await response.json()).props.entry.title).toBe(content.title);
    const unrelated = await get(
      request,
      `${fixture.primaryArticle.url}?token=${content.token}`,
    );
    expect((await unrelated.json()).props.entry.title).toBe(
      fixture.primaryArticle.title,
    );
    expect((await unrelated.json()).props.isPreview).toBe(false);
  }
});

test("invalid expired unrelated and forged preview inputs cannot reveal private versions", async ({
  request,
}) => {
  const site = fixture.sites.dutch;
  for (const query of [
    "x-craft-preview=forged&draftId=1&revisionId=1&siteId=2",
    "token=does-not-exist&x-craft-preview=forged",
    `token=${original.namedDraft.expiredToken}`,
    `token=${fixture.sites.dutchDomain.draft.token}`,
  ]) {
    const response = await get(request, `${site.article.url}?${query}`);
    expect(response.status()).toBe(200);
    const { props } = await response.json();
    expect(props.entry.title).toBe(site.article.title);
    expect(props.site.id).toBe(site.id);
    expect(props.isPreview).toBe(false);
    expect(JSON.stringify(props)).not.toContain("concept geheim");
  }
  for (const entry of [original.unpublished, original.disabled]) {
    const response = await get(
      request,
      `${entry.url}?x-craft-preview=forged&draftId=${original.namedDraft.draftId}`,
    );
    expect(response.status()).toBe(404);
    expect(await response.text()).not.toContain(entry.title);
  }
});
