<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import Layout from '../Layouts/Layout.vue'
import NewsletterForms from '../Components/NewsletterForms.vue'

// Persistent layout: mounted once, kept across visits (the /api/me island loads once).
defineOptions({ layout: Layout })
defineProps<{ renderedAt: string }>()
</script>

<template>
  <Head title="About" />
  <h1 class="text-2xl font-bold">About (static)</h1>
  <p>Rendered at the origin at <span data-testid="rendered-at">{{ renderedAt }}</span></p>
  <NewsletterForms />
</template>
