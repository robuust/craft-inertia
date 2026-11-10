<script setup>
import { Head, Link, usePage } from "@inertiajs/vue3";
defineProps({ contactUrl: String });
const page = usePage();
</script>

<template>
  <main>
    <Head title="Contact">
      <meta name="robots" content="noindex, nofollow" />
    </Head>
    <h1>Contact</h1>
    <p v-if="page.flash.success" role="status">{{ page.flash.success }}</p>
    <Link :href="contactUrl">Back to contact</Link>
  </main>
</template>
