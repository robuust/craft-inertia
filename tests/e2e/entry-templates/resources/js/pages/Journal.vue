<script setup>
import { Head, Link } from "@inertiajs/vue3";
defineProps(["entry", "seo"]);
</script>
<template>
  <Head :title="seo.title" />
  <main>
    <h1>{{ entry.title }}</h1>
    <p>{{ entry.fields.teaser }}</p>
    <p>{{ entry.fields.copy }}</p>
    <Link href="/">Home</Link>
  </main>
</template>
