import { test, expect } from "@playwright/test";
import type { APIResponse, Page } from "@playwright/test";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const fixtures = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);
const configPath = `${runtime}/playground/config/site-content.php`;
const originalConfig = readFileSync(configPath, "utf8");
const inertiaHeaders = {
  "X-Inertia": "true",
  "X-Requested-With": "XMLHttpRequest",
  "X-Inertia-Version": fixtureCommand("asset-version").version,
};
const pathOf = (url: string) => new URL(url).pathname;
const previewUrl = (fixture: { url: string; token: string }) =>
  `${fixture.url}?token=${encodeURIComponent(fixture.token)}`;

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

function changeSchema(
  section: string,
  summaryField = "summary",
  bodyField = "body",
  projects = "projects",
  locationField = "location",
) {
  writeFileSync(
    configPath,
    `<?php return [
    'articles' => ['section' => '${section}', 'summaryField' => '${summaryField}', 'bodyField' => '${bodyField}'],
    'projects' => ['section' => '${projects}', 'locationField' => '${locationField}'],
  ];\n`,
  );
}

async function initialPage(page: Page) {
  return JSON.parse(
    (await page.locator("script[data-page=app]").textContent())!,
  );
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

test("homepage single renders at the native root URL", async ({
  page,
  request,
}) => {
  const response = await page.goto("/");
  expect(response?.status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "E2E homepage" }),
  ).toBeVisible();
  await expect(page.locator("main")).toContainText(
    "A project-owned public frontend.",
  );
  expect((await initialPage(page)).component).toBe("Site/Home");
  const visit = await request.get("/", { headers: inertiaHeaders });
  expect((await visit.json()).component).toBe("Site/Home");
  expect((await visit.json()).props.entry.url).toBe(fixtures.home.url);
});

test("overview shows 20 live entries and links to their native Craft URLs", async ({
  page,
}) => {
  const response = await page.goto("/articles");
  expect(response?.status()).toBe(200);
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
  ).toBeVisible();
  await expect(page.locator("main li")).toHaveCount(20);
  await expect(page.locator("main h2")).toHaveText(
    fixtures.published
      .slice(-20)
      .reverse()
      .map((entry: { title: string }) => entry.title),
  );
  await expect(
    page.getByRole("link", { name: fixtures.published.at(-1).title }),
  ).toHaveAttribute("href", fixtures.published.at(-1).url);
});

test("native named destination agrees on direct and Inertia representations", async ({
  page,
  request,
}) => {
  const entry = fixtures.published.at(-1);
  const response = await page.goto(entry.url);
  expect(response?.status()).toBe(200);
  await expect(page.getByRole("heading", { name: entry.title })).toBeVisible();
  await expect(
    page.getByText(`Summary for ${entry.title}`, { exact: true }),
  ).toBeVisible();
  await expect(page.locator("main article .whitespace-pre-wrap")).toHaveText(
    `Published body for ${entry.title}\nSecond paragraph.`,
  );
  await expect(page.locator("time")).toHaveAttribute("datetime", "2020-01-23");
  const direct = await initialPage(page);
  const visit = await request.get(entry.url, { headers: inertiaHeaders });
  expect(visit.headers()["x-inertia"]).toBe("true");
  const json = await visit.json();
  expect(json.component).toBe("Site/Articles/Show");
  expect(json.props).toEqual(direct.props);
  expect(json.props.entry.url).toBe(entry.url);
});

test("controller destination uses a different project page and field projection", async ({
  page,
  request,
}) => {
  await page.goto(fixtures.project.url);
  await expect(
    page.getByRole("heading", { name: fixtures.project.title }),
  ).toBeVisible();
  await expect(page.locator("main")).toContainText("Location: Utrecht");
  const direct = await initialPage(page);
  const visit = await request.get(fixtures.project.url, {
    headers: inertiaHeaders,
  });
  const json = await visit.json();
  expect(json.component).toBe("Site/Projects/Show");
  expect(json.props).toEqual(direct.props);
  expect(Object.keys(json.props.entry).sort()).toEqual([
    "blocks",
    "id",
    "image",
    "location",
    "related",
    "richText",
    "title",
    "topics",
    "url",
  ]);
});

for (const kind of ["nativeNamed", "nativeController"] as const) {
  test(`${kind} matching destination retains its registered middleware pipeline`, async ({
    request,
  }) => {
    const response = await request.get(fixtures[kind].url);
    expect(response.status()).toBe(200);
    expect(response.headers()["x-e2e-route-middleware"]).toBe("applied");
    expect(await response.json()).toEqual({ title: fixtures[kind].title });
  });
}

for (const kind of [
  "namedUri",
  "namedDomain",
  "namedMethod",
  "controllerUri",
  "controllerDomain",
  "controllerMethod",
] as const) {
  test(`${kind} mismatch cannot dispatch its native destination`, async ({
    request,
  }) => {
    const fixture = fixtures.routingMismatches[kind];
    const response = await request.get(fixture.url, {
      headers: { Accept: "application/json" },
    });
    expect(response.status()).toBe(500);
    const json = await response.json();
    expect(json.message).toContain("does not match the entry URL");
    expect(json).not.toHaveProperty("title");
    expect(await response.text()).not.toContain(fixture.title);
  });
}

test("project configuration supports another schema without package changes", async ({
  page,
  request,
}) => {
  changeSchema("journal", "teaser", "copy", "caseStudies", "city");
  await page.goto("/articles");
  await expect(page.locator("main h2")).toHaveText(["E2E journal entry"]);
  await page.getByRole("link", { name: "E2E journal entry" }).click();
  await expect(
    page.getByText("An alternate teaser", { exact: true }),
  ).toBeVisible();
  await expect(page.locator("main article .whitespace-pre-wrap")).toHaveText(
    "An alternate copy field",
  );
  const visit = await request.get(fixtures.alternateProject.url, {
    headers: inertiaHeaders,
  });
  expect((await visit.json()).props.entry.location).toBe("Amsterdam");
  await page.goto(fixtures.alternateProject.url);
  await expect(page.locator("main")).toContainText("Location: Amsterdam");
});

test("Inertia navigation and browser Back preserve the document", async ({
  page,
}) => {
  const entry = fixtures.published.at(-1);
  await page.goto("/articles");
  await expect(page.getByRole("link", { name: entry.title })).toBeVisible();
  await page.evaluate(() => {
    (window as unknown as Record<string, unknown>).__pocNavigation =
      "same-document";
  });
  const responsePromise = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname === pathOf(entry.url) &&
      response.request().headers()["x-inertia"] === "true",
  );
  await page.getByRole("link", { name: entry.title }).click();
  const response = await responsePromise;
  expect((await response.json()).component).toBe("Site/Articles/Show");
  await expect(page.getByRole("heading", { name: entry.title })).toBeVisible();
  await page.goBack();
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
  ).toBeVisible();
  expect(
    await page.evaluate(
      () => (window as unknown as Record<string, unknown>).__pocNavigation,
    ),
  ).toBe("same-document");
});

for (const kind of ["disabled", "future", "expired", "unpublished"] as const) {
  test(`${kind} entry remains unavailable on native URLs in both representations`, async ({
    page,
    request,
  }) => {
    const response = await page.goto(fixtures[kind].url);
    expect(response?.status()).toBe(404);
    await expect(page.locator("body")).not.toContainText(fixtures[kind].title);
    const visit = await request.get(fixtures[kind].url, {
      headers: inertiaHeaders,
    });
    expect(visit.status()).toBe(404);
    expect(await visit.text()).not.toContain(fixtures[kind].title);
  });
}

test("unknown native URL and removed numeric demo URL return 404", async ({
  request,
}) => {
  for (const url of [
    "/articles/does-not-exist",
    "/inertia-poc",
    "/inertia-poc/entries/99999999",
  ]) {
    const response = await request.get(url);
    expect(response.status()).toBe(404);
  }
});

test("missing optional project fields render without errors", async ({
  page,
}) => {
  await page.goto(fixtures.minimal.url);
  await expect(
    page.getByRole("heading", { name: "E2E minimal entry" }),
  ).toBeVisible();
  await expect(page.locator("main article .whitespace-pre-wrap")).toHaveCount(
    0,
  );
});

test("project Vue text rendering escapes title and field values", async ({
  page,
}) => {
  await page.goto(fixtures.xss.url);
  await expect(
    page.getByRole("heading", {
      name: '<img src=x onerror="window.__pocXss=1">',
    }),
  ).toBeVisible();
  await expect(page.locator("main")).toContainText(
    "<script>window.__pocXss=2</script>",
  );
  await expect(page.locator("main article .whitespace-pre-wrap")).toHaveText(
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
  ["pocEmpty", "No published entries match your filters."],
  ["pocMissing", "The article section has not been configured."],
] as const) {
  test(`${section} renders its project empty state`, async ({ page }) => {
    changeSchema(section);
    await page.goto("/articles");
    await expect(page.getByRole("status")).toHaveText(message);
    await expect(page.locator("main li")).toHaveCount(0);
  });
}

test("configured dynamic view route wins an identical GET and HEAD signature", async ({
  page,
  request,
}) => {
  fixtureCommand("dynamic-route", "on");
  try {
    const response = await page.goto("/articles");
    expect(response?.status()).toBe(200);
    await expect(
      page.getByRole("heading", { name: "Craft dynamic route wins" }),
    ).toBeVisible();
    expect(response?.headers()["x-inertia"]).toBeUndefined();
    await expect(page.locator("script[data-page=app]")).toHaveCount(0);
    const head = await request.head("/articles");
    expect(head.status()).toBe(200);
    expect(head.headers()["x-inertia"]).toBeUndefined();
  } finally {
    fixtureCommand("dynamic-route", "off");
  }
  await page.goto("/articles");
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
  ).toBeVisible();
});

test("public and preview HTML and Inertia JSON remain private and non-cacheable", async ({
  request,
}) => {
  for (const url of [
    fixtures.published.at(-1).url,
    previewUrl(fixtures.namedDraft),
  ]) {
    const html = await request.get(url);
    expect(html.status()).toBe(200);
    expectPrivate(html);
    const json = await request.get(url, { headers: inertiaHeaders });
    expect(json.status()).toBe(200);
    expectPrivate(json);
    expect(json.headers()["x-inertia"]).toBe("true");
  }
});

test("native named preview uses Craft authorization and preserves canonical public content", async ({
  page,
  request,
}) => {
  await page.goto(previewUrl(fixtures.namedDraft));
  await expect(
    page.getByRole("heading", { name: "E2E named draft secret" }),
  ).toBeVisible();
  await expect(page.getByRole("status")).toContainText("Live Preview");
  await expect(page.locator("main article .whitespace-pre-wrap")).toHaveText(
    "Named draft body secret",
  );
  const publicVisit = await request.get(fixtures.namedDraft.url, {
    headers: inertiaHeaders,
  });
  expect((await publicVisit.json()).props.entry.title).toBe(
    "E2E named draft published",
  );
  expect(await publicVisit.text()).not.toContain("secret");
});

test("unpublished native preview requires a valid token", async ({ page }) => {
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
    await page.goto(`${fixtures.namedDraft.url}?${parameters}`);
    await expect(
      page.getByRole("heading", { name: "E2E named draft published" }),
    ).toBeVisible();
    await expect(page.locator("main")).not.toContainText("secret");
    await expect(page.getByRole("status")).toHaveCount(0);
  });
}

test("unrelated token grants no access to another unpublished native URL", async ({
  request,
}) => {
  const response = await request.get(
    `${fixtures.unpublished.url}?token=${encodeURIComponent(fixtures.namedDraft.token)}`,
    { headers: inertiaHeaders },
  );
  expect(response.status()).toBe(404);
  expect(await response.text()).not.toContain("Unpublished body secret");
});

test("overview excludes preview placeholders", async ({ page }) => {
  await page.goto(
    `/articles?token=${encodeURIComponent(fixtures.namedDraft.token)}`,
  );
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
  ).toBeVisible();
  await expect(page.locator("main")).not.toContainText("secret");
  await expect(page.locator("main li")).toHaveCount(20);
});

test("mobile overview and detail stay within the viewport", async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/articles");
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
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

test("public shell keeps project shares and excludes CP data while CP retains its shell", async ({
  page,
}) => {
  await login(page);
  await page.goto("/articles");
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
  ).toBeVisible();
  const initial = await initialPage(page);
  expect(initial.props).not.toHaveProperty("craft");
  expect(initial.props.auth.user.email).toBe(credentials.email);
  const scripts = await page
    .locator("script[src]")
    .evaluateAll((nodes) => nodes.map((node) => node.getAttribute("src")));
  expect(scripts.some((src) => src?.includes("/build/assets/"))).toBe(true);
  expect(scripts.some((src) => src?.includes("/vendor/craft/build/"))).toBe(
    false,
  );
  expect(
    scripts.some((src) => src?.includes("/vendor/robuust/craft-inertia-poc/")),
  ).toBe(false);
  await page.goto("/admin/dashboard");
  await expect(
    page.getByRole("heading", { name: "Dashboard", exact: true }),
  ).toBeVisible();
  expect((await initialPage(page)).component).not.toMatch(/^Site\//);
});

test("assets, native actions and GraphQL keep their own response handling", async ({
  page,
  request,
}) => {
  await page.goto("/articles");
  const asset = await page.locator("script[src]").first().getAttribute("src");
  const assetResponse = await request.get(asset!);
  expect(assetResponse.status()).toBe(200);
  expect(assetResponse.headers()["content-type"]).toMatch(/javascript/);
  expect(assetResponse.headers()["x-inertia"]).toBeUndefined();
  const action = await request.get("/actions/does-not-exist", {
    headers: { Accept: "application/json" },
  });
  expect(action.status()).toBe(404);
  expect(action.headers()["x-inertia"]).toBeUndefined();
  expect(await action.text()).not.toContain('"component":"Site/');
  const graphql = await request.get(
    "/actions/graphql/api?query=%7B__typename%7D",
    { headers: { Accept: "application/json" } },
  );
  expect(graphql.headers()["content-type"]).toContain("application/json");
  expect(graphql.headers()["x-inertia"]).toBeUndefined();
  expect(graphql.status()).toBe(400);
  expect((await graphql.json()).message).toBe("Missing Authorization header");
});

test("host SSR remains separate from explicitly enabled guest public SSR", async ({
  page,
}) => {
  await page.goto("/login");
  await expect(page.locator("#app")).toHaveAttribute(
    "data-server-rendered",
    "true",
  );
  await page.goto("/articles");
  await expect(
    page.getByRole("heading", { name: "Articles", exact: true }),
  ).toBeVisible();
  expect(await page.locator("#app").getAttribute("data-server-rendered")).toBe(
    "true",
  );
  await page.goto("/login");
  await expect(page.locator("#app")).toHaveAttribute(
    "data-server-rendered",
    "true",
  );
});

test("publishing a named draft updates its native public URL", async ({
  page,
}) => {
  const fixture = fixtureCommand("create-publish-case");
  await page.goto(fixture.url);
  await expect(
    page.getByRole("heading", { name: fixture.before }),
  ).toBeVisible();
  fixtureCommand("publish", String(fixture.draftId));
  await page.reload();
  await expect(
    page.getByRole("heading", { name: fixture.after }),
  ).toBeVisible();
  await expect(page.locator("main article .whitespace-pre-wrap")).toHaveText(
    "Published from named draft",
  );
  await expect(page.getByRole("status")).toHaveCount(0);
});
