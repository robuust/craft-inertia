import { createInertiaApp } from "@inertiajs/vue3";
import createServer from "@inertiajs/vue3/server";
import { createSSRApp, h } from "vue";
import { renderToString } from "vue/server-renderer";
const pages = import.meta.glob("./pages/**/*.vue", { eager: true });
createServer(
  (page) =>
    createInertiaApp({
      page,
      render: renderToString,
      resolve: (name) => pages[`./pages/${name}.vue`],
      setup({ App, props, plugin }) {
        return createSSRApp({ render: () => h(App, props) }).use(plugin);
      },
    }),
  { port: 13729, host: "127.0.0.1" },
);
