<script setup>
import { Head, Link } from "@inertiajs/vue3";

defineProps({ title: String, links: Object });
</script>

<template>
  <main>
    <Head :title="title" />
    <h1>{{ title }}</h1>
    <Link :href="links.home">Home</Link>
    <Link :href="links.about">About</Link>
    <a href="/native">Native page</a>
  </main>
</template>
