import { createInertiaApp } from "@inertiajs/vue3";
import { createSSRApp, h } from "vue";
import Page from "./pages/Page.vue";

createInertiaApp({
  resolve: (name) => {
    if (name !== "Page") throw new Error(`Unknown project page: ${name}`);
    return Page;
  },
  setup({ el, App, props, plugin }) {
    createSSRApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
