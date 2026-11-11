<script setup>
import { Head, Link } from "@inertiajs/vue3";
defineProps(["entry", "seo"]);
</script>
<template>
  <Head :title="seo.title" />
  <main>
    <h1>{{ entry.title }}</h1>
    <p>{{ entry.fields.location }}</p>
    <Link href="/">Home</Link>
  </main>
</template>
