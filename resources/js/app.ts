import { createInertiaApp } from "@inertiajs/vue3";
import { createApp, h } from "vue";
import type { DefineComponent } from "vue";
import "../css/app.css";

const pages = import.meta.glob<{ default: DefineComponent }>(
  "./pages/**/*.vue",
);

createInertiaApp({
  title: (title) => `${title} · Craft 6 Inertia proof of concept`,
  resolve: async (name) => {
    const load = pages[`./pages/${name}.vue`];

    if (!load) {
      throw new Error(`Unknown proof-of-concept page: ${name}`);
    }

    return (await load()).default;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
