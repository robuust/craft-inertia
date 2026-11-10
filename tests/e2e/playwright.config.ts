import { defineConfig } from "@playwright/test";
import { fileURLToPath } from "node:url";

const root = fileURLToPath(new URL("../../", import.meta.url));
const evidence = process.env.POC_E2E_REPORT_DIR ?? `${root}/reports/e2e/step03`;

export default defineConfig({
  testDir: ".",
  projects: [
    { name: "step01", testMatch: "plugin.spec.ts" },
    { name: "step02", testMatch: "content.spec.ts" },
    {
      name: "step03",
      testMatch: "sites.spec.ts",
      dependencies: ["step01", "step02"],
    },
    {
      name: "editor-preview",
      testMatch: "editor-preview.spec.ts",
      dependencies: ["step03"],
    },
    {
      name: "step06b",
      testMatch: "indexing.spec.ts",
      dependencies: ["step03"],
    },
    {
      name: "step06a",
      testMatch: "rendering.spec.ts",
      dependencies: ["step03"],
    },
    {
      name: "step04",
      testMatch: "forms.spec.ts",
      dependencies: ["step03"],
    },
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
    launchOptions: {
      args: [
        "--host-resolver-rules=MAP nl.craft.test 127.0.0.1",
        "--no-proxy-server",
      ],
    },
  },
});
