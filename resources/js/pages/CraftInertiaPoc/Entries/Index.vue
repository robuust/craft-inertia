<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import PocLayout from "../../../layouts/PocLayout.vue";
import type { EntrySummary, PageProps } from "../../../types";

defineProps<
  PageProps & {
    entries: EntrySummary[];
    section: {
      handle: string;
      exists: boolean;
    };
  }
>();
</script>

<template>
  <PocLayout :index-url="indexUrl" :site="site">
    <Head title="Entries" />

    <h1>Entries</h1>
    <p>Latest 20 published entries from the {{ section.handle }} section.</p>

    <p v-if="!section.exists" role="status">
      Create a Craft section with handle <code>{{ section.handle }}</code
      >, or update <code>config/inertia-poc.php</code> to use an existing
      section.
    </p>
    <p v-else-if="entries.length === 0" role="status">
      No published entries yet. Publish an entry in Craft to see it here.
    </p>

    <ul v-else class="entries">
      <li v-for="entry in entries" :key="entry.id">
        <article>
          <h2>
            <Link :href="entry.url">{{ entry.title }}</Link>
          </h2>
          <time v-if="entry.postDate" :datetime="entry.postDate">{{
            entry.postDate
          }}</time>
          <p v-if="entry.summary">{{ entry.summary }}</p>
        </article>
      </li>
    </ul>
  </PocLayout>
</template>
