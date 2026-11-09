<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import PocLayout from "../../../layouts/PocLayout.vue";
import type { EntryDetail, PageProps } from "../../../types";

defineProps<
  PageProps & {
    entry: EntryDetail;
    isPreview: boolean;
  }
>();
</script>

<template>
  <PocLayout :index-url="indexUrl" :site="site">
    <Head :title="entry.title" />

    <p v-if="isPreview" class="preview-notice" role="status">
      Live Preview — these changes have not been published.
    </p>

    <nav aria-label="Entry navigation">
      <a v-if="isPreview" :href="indexUrl">Back to published entries</a>
      <Link v-else :href="indexUrl">Back to entries</Link>
    </nav>

    <article>
      <h1>{{ entry.title }}</h1>
      <time v-if="entry.postDate" :datetime="entry.postDate">{{
        entry.postDate
      }}</time>
      <p v-if="entry.summary">{{ entry.summary }}</p>
      <div v-if="entry.body" class="entry-body">{{ entry.body }}</div>
    </article>
  </PocLayout>
</template>
