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
    <p>{{ entry.fields.introduction }}</p>
    <Link href="/articles/first">Read article</Link>
    <Link href="/journal/first">Read journal</Link>
    <Link href="/places/parent/child">Read nested page</Link>
  </main>
</template>
