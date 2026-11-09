import { defineConfig } from "@playwright/test";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const evidence = process.env.POC_E2E_REPORT_DIR ?? `${root}/reports/e2e/step01`;

export default defineConfig({
  testDir: ".",
  projects: [
    { name: "step01", testMatch: "plugin.spec.ts" },
    { name: "step02", testMatch: "content.spec.ts" },
    { name: "editor-preview", testMatch: "editor-preview.spec.ts" },
  ],
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: true,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  outputDir: `${evidence}/results`,
  reporter: [
    ["list"],
    ["html", { outputFolder: `${evidence}/playwright`, open: "never" }],
    ["json", { outputFile: `${evidence}/results.json` }],
    ["junit", { outputFile: `${evidence}/junit.xml` }],
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
