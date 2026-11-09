import { defineConfig } from "@playwright/test";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));

export default defineConfig({
  testDir: ".",
  testMatch: "**/*.spec.ts",
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: true,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  outputDir: `${root}/reports/e2e/results`,
  reporter: [
    ["list"],
    ["html", { outputFolder: `${root}/reports/e2e/playwright`, open: "never" }],
    ["json", { outputFile: `${root}/reports/e2e/results.json` }],
    ["junit", { outputFile: `${root}/reports/e2e/junit.xml` }],
  ],
  use: {
    channel: "chrome",
    baseURL: "http://127.0.0.1:18766",
    viewport: { width: 1440, height: 1000 },
    screenshot: "on",
    trace: "retain-on-failure",
    actionTimeout: 10_000,
  },
});
