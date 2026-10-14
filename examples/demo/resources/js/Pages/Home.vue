<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3'
import Layout from '../Layouts/Layout.vue'

// Persistent layout: mounted once, kept across visits (the /api/me island loads once).
defineOptions({ layout: Layout })

defineProps<{ renderedAt: string }>()

// Polling a static page is a partial reload: it must go to the origin, uncached.
const { start, stop, polling } = usePoll(1500, { only: ['renderedAt'] }, { autoStart: false })
</script>

<template>
  <Head title="Home" />
  <h1 class="text-2xl font-bold">Static home</h1>
  <p>Rendered at the origin at <span data-testid="rendered-at">{{ renderedAt }}</span></p>

  <div class="mt-3 flex gap-3 text-sm">
    <Link href="/blog/post-11" prefetch data-testid="prefetch-link" class="underline">Post 11 (prefetch on hover)</Link>
    <button v-if="!polling" class="rounded border px-2" data-testid="poll-start" @click="start">Start polling</button>
    <button v-else class="rounded border px-2" data-testid="poll-stop" @click="stop">Stop polling</button>
  </div>
</template>
