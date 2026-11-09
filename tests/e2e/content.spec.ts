import { test, expect } from "@playwright/test";
import type { Page } from "@playwright/test";
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";

const runtime = fileURLToPath(new URL("./.runtime/", import.meta.url));
const fixtures = JSON.parse(readFileSync(`${runtime}/fixtures.json`, "utf8"));
const configPath = `${runtime}/playground/config/site-content.php`;
const originalConfig = readFileSync(configPath, "utf8");
const output = execFileSync(
  "php",
  [fileURLToPath(new URL("./fixtures.php", import.meta.url)), "asset-version"],
  { cwd: `${runtime}/playground`, encoding: "utf8" },
);
const headers = {
  "X-Inertia": "true",
  "X-Inertia-Version": JSON.parse(output.split("POC_FIXTURES_JSON=").at(-1)!)
    .version,
};

const pageErrors = new WeakMap<Page, string[]>();
test.beforeEach(({ page }) => {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on("pageerror", (error) => errors.push(error.message));
});
test.afterEach(({ page }) => {
  writeFileSync(configPath, originalConfig);
  expect(pageErrors.get(page)).toEqual([]);
});

test("native rich text, relations, nested Matrix, globals and transforms use explicit public props", async ({
  page,
  request,
}) => {
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  await page.goto(fixtures.content.article.url);
  await expect(
    page.getByRole("heading", { name: "Native rich text" }),
  ).toBeVisible();
  await expect(
    page.getByRole("heading", { name: "Article nested heading" }),
  ).toBeVisible();
  await expect(page.locator("footer")).toContainText(fixtures.content.tagline);
  await expect(
    page
      .getByRole("navigation", { name: "Related articles" })
      .getByRole("link"),
  ).toHaveAttribute("href", fixtures.content.related.url);
  const image = page.getByRole("img", { name: fixtures.content.image.alt });
  await expect(image).toBeVisible();
  expect(
    await image.evaluate((img: HTMLImageElement) => img.naturalWidth),
  ).toBeGreaterThan(0);
  const response = await request.get(fixtures.content.article.url, { headers });
  const props = (await response.json()).props;
  expect(props.entry.richText).toBe(fixtures.content.richText);
  expect(props.entry.image).toEqual(fixtures.content.image);
  expect(props.entry.related.map((entry: { id: number }) => entry.id)).toEqual([
    fixtures.content.related.id,
  ]);
  expect(
    props.entry.topics.map((topic: { title: string }) => topic.title),
  ).toEqual(["Architecture", "Culture"]);
  expect(Object.keys(props.entry).sort()).toEqual(
    [
      "id",
      "title",
      "url",
      "postDate",
      "summary",
      "body",
      "richText",
      "related",
      "topics",
      "image",
      "blocks",
    ].sort(),
  );
  expect(Object.keys(props.entry.blocks[0]).sort()).toEqual(
    ["id", "type", "heading", "text", "children"].sort(),
  );
  expect(props.entry.blocks).toHaveLength(2);
  expect(props.entry.blocks[1].children[0].text).toContain(
    "<strong>Markdown</strong>",
  );
  const serialized = JSON.stringify(props);
  for (const secret of [
    "fieldLayout",
    "password",
    "authorId",
    "Unsupported block internal secret",
    "Disabled block secret",
    "Unpublished topic secret",
    "E2E disabled secret",
  ])
    expect(serialized).not.toContain(secret);
  expect(
    await page.evaluate(
      () => (window as unknown as { __contentXss?: number }).__contentXss,
    ),
  ).toBeUndefined();
  expect(props.entry.richText).not.toMatch(/<script|javascript:/i);
  expect(errors).toEqual([]);
});

test("missing optional fields and distinct project schema render predictably", async ({
  page,
  request,
}) => {
  const minimal = (
    await (await request.get(fixtures.minimal.url, { headers })).json()
  ).props.entry;
  expect(minimal).toMatchObject({
    summary: "",
    body: "",
    richText: "",
    related: [],
    topics: [],
    image: null,
    blocks: [],
  });
  await page.goto(fixtures.minimal.url);
  await expect(
    page.getByRole("heading", { name: fixtures.minimal.title }),
  ).toBeVisible();
  await page.goto(fixtures.project.url);
  await expect(
    page.getByRole("heading", { name: "Project nested heading" }),
  ).toBeVisible();
  const project = (
    await (await request.get(fixtures.project.url, { headers })).json()
  ).props.entry;
  expect(project.location).toBe("Utrecht");
  expect(project.image).toEqual(fixtures.content.image);
  expect(project).not.toHaveProperty("body");
});

test("a second content schema uses different field handles with the same package", async ({
  page,
  request,
}) => {
  writeFileSync(
    configPath,
    originalConfig
      .replace("'section' => 'articles'", "'section' => 'journal'")
      .replace("'summaryField' => 'summary'", "'summaryField' => 'teaser'")
      .replace("'bodyField' => 'body'", "'bodyField' => 'copy'")
      .replace("'richTextField' => 'richText'", "'richTextField' => 'richCopy'")
      .replace(
        "'relatedField' => 'relatedArticles'",
        "'relatedField' => 'relatedStories'",
      )
      .replace("'topicsField' => 'topics'", "'topicsField' => 'themes'")
      .replace("'imageField' => 'heroImage'", "'imageField' => 'coverImage'")
      .replace(
        "'blocksField' => 'contentBlocks'",
        "'blocksField' => 'storyBlocks'",
      )
      .replace("'section' => 'projects'", "'section' => 'caseStudies'")
      .replace("'locationField' => 'location'", "'locationField' => 'city'")
      .replace("'imageField' => 'projectImage'", "'imageField' => 'caseImage'")
      .replace(
        "'blocksField' => 'projectContent'",
        "'blocksField' => 'caseContent'",
      ),
  );
  await page.goto(fixtures.alternateArticle.url);
  await expect(
    page.getByRole("heading", { name: "Journal nested heading" }),
  ).toBeVisible();
  const entry = (
    await (await request.get(fixtures.alternateArticle.url, { headers })).json()
  ).props.entry;
  expect(entry.richText).toBe(fixtures.content.alternateRichText);
  expect(entry.summary).toBe("An alternate teaser");
  expect(entry.image).toEqual(fixtures.content.image);
  await page.goto(fixtures.alternateProject.url);
  await expect(
    page.getByRole("heading", { name: "Case nested heading" }),
  ).toBeVisible();
});

test("query URLs preserve search, taxonomy, sort and page through Inertia and browser history", async ({
  page,
}) => {
  await page.goto("/articles?q=E2E%20article&sort=oldest&page=2");
  await expect(page.locator("main h2")).toHaveText([
    "E2E article 21",
    "E2E article 22",
  ]);
  await expect(page.getByLabel("Search articles")).toHaveValue("E2E article");
  await expect(page.getByLabel("Sort", { exact: true })).toHaveValue("oldest");
  const previous = page.getByRole("link", { name: "Previous page" });
  await expect(previous).toHaveAttribute(
    "href",
    /q=E2E%20article.*sort=oldest.*page=1/,
  );
  await previous.click();
  await expect(page.locator("main h2")).toHaveCount(20);
  await page.goBack();
  await expect(page.locator("main h2")).toHaveText([
    "E2E article 21",
    "E2E article 22",
  ]);
  await page.getByRole("link", { name: "E2E article 22", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Article nested heading" }),
  ).toBeVisible();
  await page.goBack();
  await expect(page.getByLabel("Search articles")).toHaveValue("E2E article");
  await page.getByLabel("Topic", { exact: true }).selectOption("architecture");
  await page.getByRole("button", { name: "Apply filters" }).click();
  await expect(page).toHaveURL(/topic=architecture/);
  await expect(page).not.toHaveURL(/page=/);
  await expect(page.locator("main h2")).toHaveText([
    "E2E article 21",
    "E2E article 22",
  ]);
  await page.reload();
  await expect(page.getByLabel("Topic", { exact: true })).toHaveValue(
    "architecture",
  );
});

test("native queries bound page size, clamp pages and normalize invalid input", async ({
  request,
}) => {
  for (const [query, expected] of [
    ["?page=9999", 2],
    ["?page=-5", 1],
    ["?page[]=2&sort[]=title&q[]=bad", 1],
  ] as const) {
    const response = await request.get(`/articles${query}`, { headers });
    expect(response.status()).toBe(200);
    const props = (await response.json()).props;
    expect(props.pagination.currentPage).toBe(expected);
    expect(props.entries.length).toBeLessThanOrEqual(20);
    expect(props.filters.sort).toBe("newest");
  }
  const result = (
    await (
      await request.get(
        "/articles?q=E2E%20article&sort=title&topic=culture&page=999",
        { headers },
      )
    ).json()
  ).props;
  expect(result.entries.map((entry: { title: string }) => entry.title)).toEqual(
    ["E2E article 22"],
  );
  expect(result.pagination).toMatchObject({
    currentPage: 1,
    lastPage: 1,
    total: 1,
    nextUrl: null,
    prevUrl: null,
  });
  for (const query of [
    "q=unmatched",
    "topic=unknown",
    "topic=unpublished-topic-secret",
  ]) {
    const props = (
      await (await request.get(`/articles?${query}`, { headers })).json()
    ).props;
    expect(props.entries).toEqual([]);
    expect(props.pagination.total).toBe(0);
  }
});
