<script setup>
import { Head, Link } from "@inertiajs/vue3";
defineProps(["entry", "site", "seo", "preview"]);
</script>
<template>
  <Head :title="seo.title"
    ><link rel="canonical" :href="seo.canonical" /><meta
      name="robots"
      :content="seo.robots"
  /></Head>
  <main>
    <h1>{{ entry.title }}</h1>
    <p>{{ entry.fields.summary }}</p>
    <p>{{ entry.fields.body }}</p>
    <div v-html="entry.fields.richText" />
    <img
      v-for="image in entry.fields.heroImage"
      :key="image.id"
      :src="image.url"
      :srcset="image.srcset"
      :width="image.width"
      :height="image.height"
      :alt="image.alt"
    />
    <p>{{ site.language }}</p>
    <p v-if="preview">Preview</p>
    <Link href="/">Home</Link>
  </main>
</template>
