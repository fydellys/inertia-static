<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import Layout from '../Layouts/Layout.vue'
import NewsletterForms from '../Components/NewsletterForms.vue'

// Persistent layout: mounted once, kept across visits (the /api/me island loads once).
defineOptions({ layout: Layout })

const props = defineProps<{ renderedAt: string; secret: string; firstPost: { id: number; title: string } | null }>()
const form = useForm({ title: props.firstPost?.title ?? '' })
</script>

<template>
  <Head title="Dashboard" />
  <h1 class="text-2xl font-bold">Dashboard (dynamic)</h1>
  <p v-if="($page.props as any).flash" class="text-green-700" data-testid="flash">{{ ($page.props as any).flash }}</p>
  <p data-testid="secret">{{ secret }}</p>
  <p>Rendered at the origin at <span data-testid="rendered-at">{{ renderedAt }}</span></p>

  <form v-if="firstPost" class="mt-4 flex gap-2" @submit.prevent="form.post(`/admin/posts/${firstPost.id}`)">
    <input v-model="form.title" class="flex-1 border p-1" data-testid="post-title-input" />
    <button class="rounded bg-slate-800 px-3 text-white" data-testid="rename">Rename post {{ firstPost.id }}</button>
  </form>
  <NewsletterForms />
</template>
