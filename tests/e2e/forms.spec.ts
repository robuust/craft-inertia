import { test, expect, type Page, type APIResponse } from "@playwright/test";
import { readFileSync, existsSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const credentials = JSON.parse(
  readFileSync(`${runtime}/credentials.json`, "utf8"),
);
let fixture: any;
const sessions = new Map<string, any>();
const headers = { Accept: "application/json" };
function seed(mode: string) {
  execFileSync(
    "php",
    [fileURLToPath(new URL("./fixtures.php", import.meta.url)), mode],
    {
      cwd: `${runtime}/playground`,
      encoding: "utf8",
      timeout: 120_000,
    },
  );
}
test.beforeAll(() => {
  test.setTimeout(120_000);
  if (!existsSync(`${runtime}/sites.json`)) seed("seed-sites");
  seed("seed-files");
  seed("clean-files");
  seed("restore-form-titles");
  fixture = JSON.parse(readFileSync(`${runtime}/files.json`, "utf8"));
});
const errors = new WeakMap<Page, string[]>();
test.beforeEach(({ page }) => {
  errors.set(page, []);
  page.on("pageerror", (error) => errors.get(page)!.push(error.message));
});
test.afterEach(async ({ page }, info) => {
  await info.attach("browser-errors", {
    body: JSON.stringify(errors.get(page)),
    contentType: "application/json",
  });
  expect(errors.get(page)).toEqual([]);
});
async function csrf(page: Page) {
  return decodeURIComponent(
    (await page.context().cookies()).find(
      (cookie) => cookie.name === "XSRF-TOKEN",
    )!.value,
  );
}
async function login(page: Page, role?: string, reuse = true) {
  const key = role ?? "admin";
  if (reuse && sessions.has(key)) {
    await page.context().addCookies(sessions.get(key).cookies);
    await page.goto("/files");
    return;
  }
  await page.goto("/login");
  const response = await page.request.post("/actions/users/login", {
    headers: { ...headers, "X-XSRF-TOKEN": await csrf(page) },
    data: {
      loginName: role ? fixture.users[role].username : credentials.username,
      password: credentials.password,
    },
  });
  expect(response.status(), await response.text()).toBe(200);
  sessions.set(key, await page.context().storageState());
}
function expectPrivate(response: APIResponse) {
  expect(response.headers()["cache-control"]).toContain("private");
  expect(response.headers()["cache-control"]).toContain("no-store");
}
async function upload(
  page: Page,
  name: string,
  content = "Synthetic private file bytes",
  extra = {},
) {
  return page.request.post("/files/upload", {
    headers: { ...headers, "X-XSRF-TOKEN": await csrf(page) },
    multipart: {
      folderId: String(fixture.folderId),
      siteId: "1",
      file: { name, mimeType: "text/plain", buffer: Buffer.from(content) },
      ...extra,
    },
  });
}

for (const [prefix, articles] of [
  ["", "articles"],
  ["/nl", "artikelen"],
]) {
  test(`native form validation, old input, bags and redirect at ${prefix || "/"}`, async ({
    page,
  }) => {
    // Native saves synchronously propagate this fixture's nested content across sites.
    test.setTimeout(90_000);
    await login(page);
    await page.goto(`${prefix}/files`);
    const native = page.locator('form[action*="entries/save-entry"]');
    const originalTitle = await native.locator('[name="title"]').inputValue();
    await native.locator('[name="title"]').fill("");
    const failedPage = page.waitForResponse(
      (response) =>
        response.url().endsWith(`${prefix}/files`) &&
        response.request().method() === "GET" &&
        response.headers()["x-inertia"] === "true",
      { timeout: 30_000 },
    );
    await native.getByRole("button", { name: "Save title" }).click();
    expect((await (await failedPage).json()).props.nativeEntry.title).toBe(
      null,
    );
    await expect(native.getByRole("alert")).toContainText(
      "title field is required",
    );
    await expect(
      page.locator('#search-heading + form [role="alert"]'),
    ).toHaveCount(0);
    await expect(native.locator('[name="title"]')).toHaveValue("");
    await native.locator('[name="title"]').fill(`${originalTitle} updated`);
    await native.getByRole("button", { name: "Save title" }).click();
    await expect(page).toHaveURL(new RegExp(`${prefix}/files$`));
    await expect(page.getByRole("status")).toContainText("Entry saved");
    await expect(native.getByRole("alert")).toHaveCount(0);
    await expect(native.locator('[name="title"]')).toHaveValue(
      `${originalTitle} updated`,
    );
    const search = page.locator('form[action$="files/search"]');
    await search.locator('[name="q"]').fill("a".repeat(101));
    await search.getByRole("button").click();
    await expect(search.getByRole("alert")).toContainText("100");
    await expect(native.getByRole("alert")).toHaveCount(0);
    await expect(page.getByRole("status")).toHaveCount(0);
    const query = prefix ? "artikel" : "article";
    await search.locator('[name="q"]').fill(query);
    await search.getByRole("button").click();
    await expect(page).toHaveURL(
      new RegExp(`${prefix}/${articles}\\?q=${query}$`),
    );
    await expect(page.getByRole("status")).toContainText("Search updated");
    await expect(
      page.getByRole("link", { name: `${originalTitle} updated`, exact: true }),
    ).toBeVisible();
    await page.reload();
    await expect(
      page.getByText("Search updated.", { exact: true }),
    ).toHaveCount(0);
    await page.goto(`${prefix}/files`);
    await native.locator('[name="title"]').fill(originalTitle);
    const restored = page.waitForResponse(
      (response) =>
        response.request().method() === "POST" &&
        new URL(response.url()).pathname === "/actions/entries/save-entry",
      { timeout: 30_000 },
    );
    await native.getByRole("button", { name: "Save title" }).click();
    expect([302, 303]).toContain((await restored).status());
    await expect(page.getByRole("status")).toContainText("Entry saved", {
      timeout: 30_000,
    });
  });

  test(`message-only native failure keeps form unsuccessful at ${prefix || "/"}`, async ({
    page,
  }) => {
    await login(page);
    const jsonFailure = await page.request.post("/e2e/native-failure", {
      headers: { ...headers, "X-XSRF-TOKEN": await csrf(page) },
      data: { title: "Attempt" },
    });
    expect(jsonFailure.status()).toBe(400);
    expect(await jsonFailure.json()).toEqual({
      message: "Native action refused.",
      errorCode: "fixture-refusal",
    });
    expect(jsonFailure.headers()["x-inertia"]).toBeUndefined();
    await page.goto(`${prefix}/e2e/native-failure-page`);
    await page.getByRole("button", { name: "Save title" }).click();
    await expect(
      page.locator(
        'section[aria-labelledby="entry-heading"] form [role="alert"]',
      ),
    ).toHaveText("Native action refused.");
    await expect(page.getByText("Entry saved.", { exact: true })).toHaveCount(
      0,
    );
    await expect(
      page.getByRole("button", { name: "Save title" }),
    ).toBeEnabled();
    await page.goto(`${prefix}/files`);
    await page.getByRole("button", { name: "Save title" }).click();
    await expect(page.getByRole("status")).toContainText("Entry saved");
    await expect(
      page.locator(
        'section[aria-labelledby="entry-heading"] form [role="alert"]',
      ),
    ).toHaveCount(0);
  });
}

test("real CSRF rejects project/native submits, upload and forged return targets", async ({
  page,
}) => {
  await login(page);
  await page.goto("/files");
  for (const url of [
    "/files/search",
    "/files/upload",
    "/actions/entries/save-entry",
  ]) {
    const response = await page.request.post(url, {
      headers,
      data: { q: "x", _token: "invalid" },
      maxRedirects: 0,
    });
    expect(response.status(), await response.text()).toBe(419);
  }
  const form = page.locator('form[action*="entries/save-entry"]');
  const fields = Object.fromEntries(
    await form
      .locator("input")
      .evaluateAll((inputs) =>
        inputs.map((input) => [
          (input as HTMLInputElement).name,
          (input as HTMLInputElement).value,
        ]),
      ),
  );
  const response = await page.request.post("/actions/entries/save-entry", {
    headers: { Accept: "text/html", "X-XSRF-TOKEN": await csrf(page) },
    data: {
      ...fields,
      entryId: Number(fields.entryId),
      siteId: Number(fields.siteId),
      redirect: "https://example.invalid/forged",
    },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(400);
  expect(response.headers().location).toBeUndefined();
});

for (const prefix of ["", "/nl"]) {
  test(`native upload validation, conflict and resubmission at ${prefix || "/"}`, async ({
    page,
  }) => {
    await login(page);
    await page.goto(`${prefix}/files`);
    const form = page.locator('section[aria-labelledby="upload-heading"] form');
    await form
      .getByRole("button", { name: "Upload file", exact: true })
      .click();
    await expect(form.getByRole("alert")).toContainText(
      prefix ? "Het file veld is verplicht." : "The file field is required.",
    );
    await form.locator('[type="file"]').setInputFiles({
      name: "forbidden.php",
      mimeType: "text/plain",
      buffer: Buffer.from("synthetic"),
    });
    await form
      .getByRole("button", { name: "Upload file", exact: true })
      .click();
    await expect(form.getByRole("alert")).toContainText("Upload rejected");
    await expect(form.getByRole("status")).toHaveCount(0);
    await form.locator('[type="file"]').setInputFiles({
      name: `ui-private${prefix ? "-nl" : ""}.txt`,
      mimeType: "text/plain",
      buffer: Buffer.from("Private UI file"),
    });
    await form
      .getByRole("button", { name: "Upload file", exact: true })
      .click();
    await expect(form.getByRole("status")).toHaveText(
      `Uploaded ui-private${prefix ? "-nl" : ""}.txt`,
    );
    await expect(
      page.getByRole("link", {
        name: `Download ui-private${prefix ? "-nl" : ""}.txt`,
      }),
    ).toBeVisible();
    await expect(form.locator('[type="file"]')).toHaveValue("");
    await form.locator('[type="file"]').setInputFiles({
      name: `ui-private${prefix ? "-nl" : ""}.txt`,
      mimeType: "text/plain",
      buffer: Buffer.from("Conflicting file"),
    });
    await form
      .getByRole("button", { name: "Upload file", exact: true })
      .click();
    await expect(form.getByRole("alert")).toContainText(
      prefix ? "bestaat al" : "already exists",
    );
    await expect(form.getByRole("status")).toHaveCount(0);
    await expect(
      form.getByRole("button", { name: "Upload file", exact: true }),
    ).toBeEnabled();
  });
}

test("native file owners enforce type/size, configured destination and private byte access", async ({
  page,
  browser,
}) => {
  await login(page, "uploader");
  await page.goto("/files");
  const result = await upload(page, "protected.txt");
  expect(result.status(), await result.text()).toBe(200);
  expectPrivate(result);
  const asset = await result.json();
  expect(asset.assetId).toEqual(expect.any(Number));
  expect(asset.url).toBe(null);
  const allowed = await page.request.get(`/files/${asset.assetId}/download`);
  expect(allowed.status()).toBe(200);
  expectPrivate(allowed);
  expect(allowed.headers()["content-disposition"]).toContain("attachment");
  expect(await allowed.text()).toBe("Synthetic private file bytes");
  const publicFile = await page.request.get("/uploads/protected.txt");
  expect(publicFile.status()).toBe(404);
  for (const extra of [{ folderId: "1" }, { siteId: "999999" }]) {
    expect(
      (await upload(page, "wrong-target.txt", "Synthetic", extra)).status(),
    ).toBe(404);
  }
  const deniedContext = await browser.newContext();
  const deniedPage = await deniedContext.newPage();
  await login(deniedPage, "denied");
  await deniedPage.goto("/files");
  await expect(
    deniedPage.getByRole("link", { name: /protected.txt/ }),
  ).toHaveCount(0);
  expect((await upload(deniedPage, "denied.txt")).status()).toBe(403);
  const denied = await deniedPage.request.get(
    `/files/${asset.assetId}/download`,
    { headers },
  );
  expect(denied.status()).toBe(403);
  expect(await denied.text()).not.toContain("Synthetic private file bytes");
  expect(denied.headers()["content-disposition"]).toBeUndefined();
  const guestContext = await browser.newContext();
  const guest = await guestContext.request.get(
    `/files/${asset.assetId}/download`,
    { headers },
  );
  expect(guest.status()).toBe(401);
  expect(await guest.text()).not.toContain("protected.txt");
  const readerContext = await browser.newContext();
  const reader = await readerContext.newPage();
  await login(reader, "reader");
  const peer = await reader.request.get(`/nl/files/${asset.assetId}/download`);
  expect(peer.status()).toBe(200);
  expectPrivate(peer);
  expect(await peer.text()).toBe("Synthetic private file bytes");
  await Promise.all([
    deniedContext.close(),
    guestContext.close(),
    readerContext.close(),
  ]);
});

test("native upload session HTTP owner validates metadata and reauthorizes session access", async ({
  page,
  browser,
}) => {
  await login(page, "uploader");
  const token = await csrf(page);
  for (const [filename, size, message] of [
    ["blocked.php", 12, "extension"],
    ["oversize.txt", 2147483647, "maximum upload size"],
  ] as const) {
    const response = await page.request.post("/actions/assets/uploads", {
      headers: { ...headers, "X-XSRF-TOKEN": token },
      data: { filename, size, folderId: fixture.folderId },
    });
    expect(response.status()).toBe(422);
    expect((await response.json()).message).toContain(message);
  }
  const started = await page.request.post("/actions/assets/uploads", {
    headers: { ...headers, "X-XSRF-TOKEN": token },
    data: {
      filename: "native-session.txt",
      size: 12,
      folderId: fixture.folderId,
    },
  });
  expect(started.status()).toBe(201);
  const session = await started.json();
  expect(session.transport.type).toBe("tus");
  const otherContext = await browser.newContext();
  const other = await otherContext.newPage();
  await login(other, "reader");
  expect(
    (await other.request.get(session.urls.status, { headers })).status(),
  ).toBe(404);
  const incomplete = await page.request.post(session.urls.complete, {
    headers: { ...headers, "X-XSRF-TOKEN": token },
  });
  expect(incomplete.status()).toBe(422);
  expect((await incomplete.json()).message).toContain("incomplete");
  const cancelled = await page.request.delete(session.urls.cancel, {
    headers: { ...headers, "X-XSRF-TOKEN": token },
  });
  expect(cancelled.status()).toBe(204);
  await otherContext.close();
});

test("invalidated native sessions cannot list, upload or download private files", async ({
  browser,
}) => {
  const contexts = await Promise.all([
    browser.newContext(),
    browser.newContext(),
    browser.newContext(),
  ]);
  const pages = await Promise.all(contexts.map((context) => context.newPage()));
  for (const page of pages) {
    await login(page, "invalidated", false);
    await page.goto("/files");
  }
  const stored = await upload(
    pages[0],
    "invalidated-session.txt",
    "Synthetic invalidated-session bytes",
  );
  expect(stored.status()).toBe(200);
  const asset = await stored.json();
  const token = await csrf(pages[1]);
  try {
    seed("invalidate-file-session");
    const listing = await pages[0].request.get("/files", { headers });
    expect(listing.status()).toBe(401);
    expect(await listing.text()).not.toContain("invalidated-session.txt");
    const rejectedUpload = await pages[1].request.post("/files/upload", {
      headers: { ...headers, "X-XSRF-TOKEN": token },
      multipart: {
        folderId: String(fixture.folderId),
        siteId: "1",
        file: {
          name: "must-not-store.txt",
          mimeType: "text/plain",
          buffer: Buffer.from("Refused session bytes"),
        },
      },
    });
    expect(rejectedUpload.status()).toBe(401);
    const download = await pages[2].request.get(
      `/files/${asset.assetId}/download`,
      { headers },
    );
    expect(download.status()).toBe(401);
    expect(download.headers()["content-disposition"]).toBeUndefined();
    expect(await download.text()).not.toContain(
      "Synthetic invalidated-session bytes",
    );
  } finally {
    seed("restore-file-session");
    await Promise.all(contexts.map((context) => context.close()));
  }
});

test("multipart upload retains the native sixty-per-minute initiation limit", async ({
  page,
}) => {
  test.setTimeout(90_000);
  await login(page, "throttled");
  const token = await csrf(page);
  for (let attempt = 0; attempt < 60; attempt++) {
    const response = await page.request.post("/files/upload", {
      headers: { ...headers, "X-XSRF-TOKEN": token },
      multipart: { folderId: String(fixture.folderId), siteId: "1" },
    });
    expect(response.status()).toBe(422);
  }
  const limited = await page.request.post("/files/upload", {
    headers: { ...headers, "X-XSRF-TOKEN": token },
    multipart: { folderId: String(fixture.folderId), siteId: "1" },
  });
  expect(limited.status()).toBe(429);
  expect(Number(limited.headers()["retry-after"])).toBeGreaterThan(0);
});
