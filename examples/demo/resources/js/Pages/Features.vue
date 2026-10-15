<script setup lang="ts">
import { Deferred, Form, Head, InfiniteScroll, Link, router, useRemember, WhenVisible } from '@inertiajs/vue3'
import { V3Features } from '@inertia-version'
import Layout from '../Layouts/Layout.vue'

// Inertia features on a static (ISR) page. Each section has its own end-to-end check.
defineOptions({ layout: Layout })

defineProps<{
  title: string
  renderedAt: string
  deferred?: string
  visible?: string
  posts: { data: { id: number; title: string }[] }
  log: string[]
  plans: string
  likes: number
}>()

const remembered = useRemember({ note: '' }, 'features-note')

const addEntry = () => router.reload({ only: ['log'] })
const resetLog = () => router.reload({ only: ['log'], reset: ['log'] })
</script>

<template>
  <Head :title="title" />
  <h1 class="text-2xl font-bold" data-testid="features-title">{{ title }}</h1>
  <p>Rendered at the origin at <span data-testid="rendered-at">{{ renderedAt }}</span></p>
  <p class="text-sm">
    <Link href="/features" data-testid="features-one">Features</Link> ·
    <Link href="/features/two" data-testid="features-two">Features two</Link>
  </p>

  <section class="mt-4 space-y-3 text-sm">
    <div>
      <b>Deferred:</b>
      <Deferred data="deferred">
        <template #fallback><span data-testid="deferred">loading…</span></template>
        <span data-testid="deferred">{{ deferred }}</span>
      </Deferred>
    </div>

    <div><b>Once:</b> <span data-testid="plans">{{ plans }}</span></div>

    <div>
      <b>Merge:</b> <span data-testid="log-count">{{ log.length }}</span> entries
      <button class="ml-2 rounded border px-1" data-testid="log-add" @click="addEntry">add</button>
      <button class="ml-1 rounded border px-1" data-testid="log-reset" @click="resetLog">reset</button>
    </div>

    <div>
      <b>Remember:</b>
      <input v-model="remembered.note" class="border p-1" data-testid="remember-input" placeholder="type, leave, come back" />
    </div>

    <Form action="/newsletter" method="post" :options="{ preserveScroll: true }" #default="{ errors, processing, wasSuccessful }">
      <b>&lt;Form&gt;:</b>
      <input name="email" class="border p-1" data-testid="form-email" placeholder="email" />
      <button type="submit" class="ml-1 rounded border px-1" :disabled="processing" data-testid="form-submit">Subscribe</button>
      <span class="ml-2 text-red-600" data-testid="form-error">{{ errors.email ?? '' }}</span>
      <span class="ml-2 text-green-700" data-testid="form-ok">{{ wasSuccessful ? 'subscribed' : '' }}</span>
    </Form>

    <component :is="V3Features" v-if="V3Features" :likes="likes" />

    <div>
      <b>Infinite scroll:</b> <span data-testid="posts-count">{{ posts.data.length }}</span> posts
      <InfiniteScroll data="posts" manual>
        <ul class="ml-4 list-disc">
          <li v-for="post in posts.data" :key="post.id" data-testid="scroll-item">{{ post.title }}</li>
        </ul>
        <template #next="{ loading, fetch, hasMore }">
          <button v-if="hasMore" class="rounded border px-1" :disabled="loading" data-testid="scroll-more" @click="fetch">load more</button>
        </template>
      </InfiniteScroll>
    </div>

    <div style="height: 1600px" class="border-l-2 border-dashed pl-2 text-xs text-slate-400">scroll down for WhenVisible</div>

    <div>
      <b>WhenVisible:</b>
      <WhenVisible data="visible">
        <template #fallback><span data-testid="visible">waiting…</span></template>
        <span data-testid="visible">{{ visible }}</span>
      </WhenVisible>
    </div>
  </section>
</template>
