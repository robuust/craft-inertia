import { defineConfig } from "@playwright/test";

const evidence = process.env.POC_INSTALL_REPORT_DIR;
if (!evidence) throw new Error("Set POC_INSTALL_REPORT_DIR.");

export default defineConfig({
  testDir: ".",
  testMatch: "install.spec.ts",
  testIgnore: "**/.runtime/**",
  workers: 1,
  fullyParallel: false,
  retries: 0,
  forbidOnly: true,
  timeout: 30_000,
  outputDir: `${evidence}/browser/results`,
  reporter: [
    ["list"],
    ["html", { outputFolder: `${evidence}/browser/playwright`, open: "never" }],
    ["json", { outputFile: `${evidence}/browser/results.json` }],
    ["junit", { outputFile: `${evidence}/browser/junit.xml` }],
  ],
  use: {
    channel: "chrome",
    baseURL: "http://127.0.0.1:18777",
    screenshot: "on",
    trace: "retain-on-failure",
  },
});
