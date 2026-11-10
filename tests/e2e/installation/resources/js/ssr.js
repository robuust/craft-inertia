import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
import Page from "./pages/Page.vue";

createServer(
  (page) =>
    createInertiaApp({
      page,
      render: renderToString,
      resolve: (name) => {
        if (name !== "Page") throw new Error(`Unknown project page: ${name}`);
        return Page;
      },
      setup({ App, props, plugin }) {
        return createSSRApp({ render: () => h(App, props) }).use(plugin);
      },
    }),
  { port: 13727, host: "127.0.0.1" },
);
