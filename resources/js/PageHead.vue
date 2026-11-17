<template>
  <Head :title="metadata.title">
    <component
      :is="'meta'"
      v-for="(tag, index) in metadata.meta"
      :key="`meta-${index}`"
      v-bind="tag"
      :head-key="`meta-${index}`"
    />
    <component
      :is="'link'"
      v-for="(tag, index) in metadata.link"
      :key="`link-${index}`"
      v-bind="tag"
      :head-key="`link-${index}`"
    />
    <component
      :is="'script'"
      v-for="script in metadata.jsonLd"
      :key="`jsonld-${script.key}`"
      type="application/ld+json"
      :head-key="`jsonld-${script.key}`"
    >
      {{ script.content }}
    </component>
  </Head>
</template>

<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

/** @type {ReturnType<typeof usePage>} Current native page, updated by Inertia navigation. */
const page = usePage();

/**
 * Read normalized metadata from the native page contract.
 * @returns {object} One title, robots policy, canonical URL and selected metadata.
 */
function resolveMetadata() {
  return page.props.head;
}

/** @type {ReturnType<typeof computed>} Calculated metadata or native entry fallback. */
const metadata = computed(resolveMetadata);
</script>
