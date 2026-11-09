<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import PocLayout from "../../layouts/PocLayout.vue";
import type { PageProps } from "../../types";

defineProps<PageProps>();
</script>

<template>
  <PocLayout :index-url="indexUrl" :site="site">
    <Head title="Entry not found" />
    <h1>Entry not found</h1>
    <p>This entry is unavailable on the public site.</p>
    <Link :href="indexUrl">Back to entries</Link>
  </PocLayout>
</template>
