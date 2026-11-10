<script setup>
import { Form, Head, usePage } from "@inertiajs/vue3";
import { nextTick, useTemplateRef } from "vue";

defineProps({ action: String, redirect: String, initial: Object });
const page = usePage();
const summary = useTemplateRef("summary");

async function focusErrors() {
  await nextTick();
  summary.value?.focus();
}
</script>

<template>
  <main>
    <Head title="Contact">
      <meta name="robots" content="noindex, nofollow" />
    </Head>
    <h1>Contact</h1>
    <p>Email and message are required.</p>
    <Form
      :action="action"
      method="post"
      error-bag="contact"
      novalidate
      :options="{ preserveScroll: true }"
      @error="focusErrors"
      v-slot="{ errors, hasErrors, processing }"
    >
      <input type="hidden" name="redirect" :value="redirect" />
      <div
        v-if="hasErrors"
        ref="summary"
        role="alert"
        tabindex="-1"
        aria-labelledby="error-heading"
      >
        <h2 id="error-heading">Please check your message</h2>
        <p v-if="page.flash.error">{{ page.flash.error }}</p>
        <p v-if="errors.message && !errors.fromEmail">{{ errors.message }}</p>
        <ul>
          <template
            v-for="field in ['fromName', 'fromEmail', 'subject', 'message']"
            :key="field"
          >
            <li v-if="errors[field]">
              <a :href="`#${field}`">{{ errors[field] }}</a>
            </li>
          </template>
        </ul>
      </div>

      <label for="fromName">Name</label>
      <input
        id="fromName"
        name="fromName"
        autocomplete="name"
        :value="initial.fromName"
        :aria-invalid="!!errors.fromName"
        :aria-describedby="errors.fromName ? 'name-error' : undefined"
      />
      <p v-if="errors.fromName" id="name-error">{{ errors.fromName }}</p>

      <label for="fromEmail">Email (required)</label>
      <input
        id="fromEmail"
        name="fromEmail"
        type="email"
        autocomplete="email"
        required
        :value="initial.fromEmail"
        :aria-invalid="!!errors.fromEmail"
        :aria-describedby="errors.fromEmail ? 'email-error' : undefined"
      />
      <p v-if="errors.fromEmail" id="email-error">{{ errors.fromEmail }}</p>

      <label for="subject">Subject</label>
      <input
        id="subject"
        name="subject"
        :value="initial.subject"
        :aria-invalid="!!errors.subject"
        :aria-describedby="errors.subject ? 'subject-error' : undefined"
      />
      <p v-if="errors.subject" id="subject-error">{{ errors.subject }}</p>

      <label for="message">Message (required)</label>
      <textarea
        id="message"
        name="message"
        required
        :value="initial.message"
        :aria-invalid="!!errors.message"
        :aria-describedby="errors.message ? 'message-error' : undefined"
      />
      <p v-if="errors.message" id="message-error">{{ errors.message }}</p>

      <button type="submit" :disabled="processing" :aria-busy="processing">
        {{ processing ? "Sending…" : "Send message" }}
      </button>
    </Form>
  </main>
</template>

<style>
main {
  max-width: 36rem;
  margin: 2rem auto;
  padding: 0 1rem;
  font: 1rem/1.5 system-ui;
}
label,
input,
textarea,
button {
  display: block;
}
label {
  margin-top: 1rem;
}
input:not([type="hidden"]),
textarea {
  box-sizing: border-box;
  width: 100%;
  padding: 0.5rem;
  font: inherit;
}
textarea {
  min-height: 9rem;
}
button {
  margin-top: 1rem;
  padding: 0.5rem 1rem;
  font: inherit;
}
:focus-visible {
  outline: 3px solid #164bc5;
  outline-offset: 3px;
}
[aria-invalid="true"] {
  border: 2px solid #a01818;
}
[role="alert"] {
  border: 2px solid #a01818;
  padding: 1rem;
}
</style>
