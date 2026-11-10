import { createInertiaApp } from "@inertiajs/vue3";
import { createSSRApp, h } from "vue";
import Page from "./pages/Page.vue";
import Contact from "./pages/Contact.vue";
import ContactThanks from "./pages/ContactThanks.vue";

const pages = { Page, Contact, ContactThanks };
createInertiaApp({
  resolve: (name) => {
    if (!pages[name]) throw new Error(`Unknown project page: ${name}`);
    return pages[name];
  },
  setup({ el, App, props, plugin }) {
    createSSRApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
