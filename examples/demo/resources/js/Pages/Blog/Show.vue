<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import Layout from '../../Layouts/Layout.vue'

// Persistent layout: mounted once, kept across visits (the /api/me island loads once).
defineOptions({ layout: Layout })

defineProps<{ renderedAt: string; canonical: string; post: { id: number; slug: string; title: string; body: string } }>()
</script>

<template>
  <Head :title="post.title">
    <meta name="description" :content="post.body" />
    <meta property="og:title" :content="post.title" />
    <meta property="og:type" content="article" />
    <link rel="canonical" :href="canonical" />
  </Head>
  <h1 class="text-2xl font-bold" data-testid="post-title">{{ post.title }}</h1>
  <p>{{ post.body }}</p>
  <p>Rendered at the origin at <span data-testid="rendered-at">{{ renderedAt }}</span></p>
  <Link href="/blog" data-testid="back">← back to the blog</Link>
</template>
