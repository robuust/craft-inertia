<script setup lang="ts">
import type { PageProps } from "../types";

defineProps<PageProps>();
</script>

<template>
  <div class="poc" :lang="site.language">
    <a class="skip-link" href="#content">Skip to content</a>

    <header>
      <p><a :href="indexUrl">Craft 6 + Inertia 3 + Vue 3</a></p>
      <p>{{ site.name }}</p>
    </header>

    <main id="content" tabindex="-1">
      <slot />
    </main>

    <footer>
      <p>Proof of concept · Content is managed in Craft.</p>
    </footer>
  </div>
</template>
