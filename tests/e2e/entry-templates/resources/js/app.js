import { createInertiaApp } from "@inertiajs/vue3";
import { createSSRApp, h } from "vue";
const pages = import.meta.glob("./pages/**/*.vue", { eager: true });
createInertiaApp({
  resolve: (name) => pages[`./pages/${name}.vue`],
  setup({ el, App, props, plugin }) {
    createSSRApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
