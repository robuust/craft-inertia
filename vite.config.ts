import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig({
  base: "./",
  publicDir: false,
  plugins: [vue()],
  build: {
    outDir: "public/build",
    emptyOutDir: true,
    manifest: "manifest.json",
    rollupOptions: {
      input: "resources/js/app.ts",
    },
  },
});
