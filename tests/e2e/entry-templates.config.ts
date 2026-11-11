import { defineConfig } from "@playwright/test";
const evidence = process.env.POC_ENTRY_REPORT_DIR;
if (!evidence)
  throw new Error("Set POC_ENTRY_REPORT_DIR to a task-owned directory.");
export default defineConfig({
  testDir: ".",
  testMatch: "entry-templates.spec.ts",
  workers: 1,
  retries: 0,
  outputDir: `${evidence}/results`,
  reporter: [["list"], ["json", { outputFile: `${evidence}/results.json` }]],
  use: {
    channel: "chrome",
    baseURL: "http://127.0.0.1:18779",
    screenshot: "on",
    trace: "retain-on-failure",
  },
});
