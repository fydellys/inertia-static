<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import Layout from '../../Layouts/Layout.vue'

// Persistent layout: mounted once, kept across visits (the /api/me island loads once).
defineOptions({ layout: Layout })

defineProps<{
  renderedAt: string
  posts: { data: { slug: string; title: string }[]; current_page: number; last_page: number }
}>()
</script>

<template>
  <Head title="Blog" />
  <h1 class="text-2xl font-bold">Blog (ISR) — page {{ posts.current_page }}</h1>
  <p>Rendered at the origin at <span data-testid="rendered-at">{{ renderedAt }}</span></p>
  <ul class="my-3 list-disc pl-6">
    <li v-for="post in posts.data" :key="post.slug">
      <Link :href="`/blog/${post.slug}`" :data-testid="`post-${post.slug}`">{{ post.title }}</Link>
    </li>
  </ul>
  <div class="flex gap-3">
    <Link v-if="posts.current_page > 1" :href="`/blog?page=${posts.current_page - 1}`" data-testid="prev">← previous</Link>
    <Link v-if="posts.current_page < posts.last_page" :href="`/blog?page=${posts.current_page + 1}`" data-testid="next">next →</Link>
  </div>
</template>
