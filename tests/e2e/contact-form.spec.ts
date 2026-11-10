import { expect, test, type APIResponse } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { readFileSync, readdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const application = fileURLToPath(
  new URL("./.runtime/contact-form/", import.meta.url),
);
const capture = path.join(application, "storage/mail-capture");
function captures() {
  try {
    return readdirSync(capture).filter((name) => name.endsWith(".eml"));
  } catch {
    return [];
  }
}
function privateResponse(response: APIResponse) {
  expect(response.headers()["cache-control"]).toContain("private");
  expect(response.headers()["cache-control"]).toContain("no-store");
  expect(response.headers()["x-robots-tag"]).toBe("noindex, nofollow");
}

test.beforeEach(() => {
  execFileSync("php", ["artisan", "cache:clear", "--no-interaction"], {
    cwd: application,
    stdio: "pipe",
  });
});

test("guest corrects native field errors then receives the native mail success", async ({
  page,
}) => {
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const before = captures();
  const first = await page.goto("/contact");
  expect(first?.headers()["cache-control"]).toContain("no-store");
  expect(first?.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  await expect(
    page.getByRole("heading", { name: "Contact", exact: true }),
  ).toBeVisible();
  await page.getByLabel("Name", { exact: true }).fill("Browser Visitor");
  await page.getByLabel("Email (required)").fill("invalid");
  await page.getByLabel("Subject", { exact: true }).fill("Browser boundary");

  let release!: () => void;
  const held = new Promise<void>((resolve) => {
    release = resolve;
  });
  await page.route("**/actions/contact-form/send", async (route) => {
    await held;
    await route.continue();
  });
  await page.getByRole("button", { name: "Send message" }).click();
  await expect(page.getByRole("button", { name: "Sending…" })).toBeDisabled();
  release();
  await expect(page.getByRole("alert")).toContainText(
    "There was a problem with your submission",
  );
  await expect(page.getByRole("alert")).toBeFocused();
  await expect(page.getByLabel("Email (required)")).toHaveAttribute(
    "aria-invalid",
    "true",
  );
  await expect(page.getByLabel("Message (required)")).toHaveAttribute(
    "aria-invalid",
    "true",
  );
  await expect(page.getByLabel("Name", { exact: true })).toHaveValue(
    "Browser Visitor",
  );
  await expect(page.getByLabel("Subject", { exact: true })).toHaveValue(
    "Browser boundary",
  );
  expect(captures()).toEqual(before);
  await page.unroute("**/actions/contact-form/send");

  await page.getByLabel("Email (required)").fill("browser@contact.test");
  await page
    .getByLabel("Message (required)")
    .fill("Private browser message <script>window.contactLeak = true</script>");
  const native = page.waitForResponse((response) =>
    response.url().endsWith("/actions/contact-form/send"),
  );
  await page.getByRole("button", { name: "Send message" }).click();
  const action = await native;
  expect(action.status()).toBe(302);
  expect(action.headers()["cache-control"]).toContain("no-store");
  expect(action.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  await expect(page).toHaveURL(/\/contact\/thanks$/);
  await expect(page.getByRole("status")).toHaveText(
    "Your message has been sent.",
  );
  expect(await page.evaluate(() => "contactLeak" in window)).toBe(false);
  const added = captures().filter((name) => !before.includes(name));
  expect(added).toHaveLength(1);
  const mail = readFileSync(path.join(capture, added[0]), "utf8");
  expect(mail).toContain("To: inbox@contact.test");
  expect(mail).toContain(
    "Reply-To: On behalf of Browser Visitor <browser@contact.test>",
  );
  expect(mail).toContain("Subject: Contact fixture - Browser boundary");
  expect(mail).toContain("Private browser message");
  expect(errors).toEqual([]);
});

test("separate visitors never inherit another session's input or flash", async ({
  page,
  browser,
}) => {
  await page.goto("/contact");
  await page.getByLabel("Name", { exact: true }).fill("Session A private name");
  await page.getByLabel("Email (required)").fill("session-a-invalid");
  await page.getByLabel("Message (required)").fill("Session A private body");
  await page.getByRole("button", { name: "Send message" }).click();
  await expect(page.getByRole("alert")).toBeVisible();
  await expect(page.getByLabel("Message (required)")).toHaveValue(
    "Session A private body",
  );
  const visitor = await browser.newContext();
  try {
    const other = await visitor.newPage();
    await other.goto("http://127.0.0.1:18778/contact");
    await expect(other.getByLabel("Name", { exact: true })).toHaveValue("");
    await expect(other.getByLabel("Message (required)")).toHaveValue("");
    await expect(other.getByRole("alert")).toHaveCount(0);
    expect(await other.content()).not.toContain("Session A private");
    const response = await visitor.request.get(
      "http://127.0.0.1:18778/contact/thanks",
      {
        headers: {
          "X-Inertia": "true",
          "X-Inertia-Version": JSON.parse(
            (await page.locator("script[data-page=app]").textContent())!,
          ).version,
        },
      },
    );
    privateResponse(response);
    expect(await response.text()).not.toContain("Session A private");
    expect((await response.json()).flash ?? {}).toEqual({});
  } finally {
    await visitor.close();
  }
});

test("native CSRF rejects absent and invalid tokens before sending", async ({
  request,
}) => {
  const before = captures();
  await request.get("/contact");
  for (const token of [undefined, "invalid-token"]) {
    const response = await request.post("/actions/contact-form/send", {
      headers: {
        Accept: "text/html",
        ...(token ? { "X-CSRF-TOKEN": token } : {}),
      },
      form: { fromEmail: "csrf@contact.test", message: "Never mail this" },
    });
    expect(response.status()).toBe(419);
    privateResponse(response);
  }
  expect(captures()).toEqual(before);
});

test("the configured native per-IP limit blocks the fourth valid-CSRF attempt", async ({
  page,
  request,
}) => {
  const before = captures();
  await page.goto("/contact");
  const cookies = await page.context().cookies();
  const token = decodeURIComponent(
    cookies.find(({ name }) => name === "XSRF-TOKEN")!.value,
  );
  const cookie = cookies
    .map(({ name, value }) => `${name}=${value}`)
    .join("; ");
  for (let attempt = 1; attempt <= 4; attempt++) {
    const response = await request.post("/actions/contact-form/send", {
      headers: {
        Accept: "text/html",
        "X-XSRF-TOKEN": token,
        Cookie: cookie,
        Referer: "http://127.0.0.1:18778/contact",
      },
      form: { fromEmail: "invalid", message: "" },
      maxRedirects: 0,
    });
    expect(response.status()).toBe(attempt < 4 ? 302 : 429);
    privateResponse(response);
    if (attempt === 4)
      expect(Number(response.headers()["retry-after"])).toBeGreaterThan(0);
  }
  expect(captures()).toEqual(before);
});

test("contact form remains usable at a narrow viewport with keyboard navigation", async ({
  page,
}) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/contact");
  await page.keyboard.press("Tab");
  await expect(page.getByLabel("Name", { exact: true })).toBeFocused();
  for (const label of ["Email (required)", "Subject", "Message (required)"]) {
    await page.keyboard.press("Tab");
    await expect(page.getByLabel(label, { exact: true })).toBeFocused();
  }
  await page.keyboard.press("Tab");
  await expect(
    page.getByRole("button", { name: "Send message" }),
  ).toBeFocused();
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
  ).toBe(true);
});
