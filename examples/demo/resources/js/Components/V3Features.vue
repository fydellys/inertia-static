<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

// Inertia 3 only: instant visits and optimistic updates.
defineProps<{ likes: number }>()

function like() {
  router.optimistic((props) => ({ likes: (props.likes as number) + 1 })).post('/features/like', {}, { preserveScroll: true })
}
</script>

<template>
  <div>
    <b>Inertia 3:</b>
    <Link href="/about" component="About" data-testid="instant-about">instant visit to About</Link>
    · likes <span data-testid="likes">{{ likes }}</span>
    <button class="ml-1 rounded border px-1" data-testid="like" @click="like">like (optimistic)</button>
  </div>
</template>
