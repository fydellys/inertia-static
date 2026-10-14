<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { useStaticIsland } from '@inertia-version'
import { computed } from 'vue'
import NetworkPanel from '../Components/NetworkPanel.vue'

const page = usePage()
const island = useStaticIsland<{ user: { id: number; name: string } | null }>('/api/me')

const sharedProps = computed(() => {
  const { appName, auth, flash, csrf, errors } = page.props as Record<string, unknown>
  return { appName, auth, flash, csrf, errors }
})

// No CSRF token needed on Laravel 13: same-origin requests pass PreventRequestForgery (Sec-Fetch-Site).
function logout() {
  router.post('/logout')
}
</script>

<template>
  <div class="mx-auto max-w-3xl p-6 font-sans">
    <header class="mb-6 flex items-center justify-between border-b pb-4">
      <strong data-testid="app-name">{{ (page.props as any).appName ?? '— no appName —' }}</strong>
      <nav class="flex gap-3 text-sm">
        <Link href="/" data-testid="nav-home">Home (static)</Link>
        <Link href="/about" data-testid="nav-about">About (static)</Link>
        <Link href="/blog" data-testid="nav-blog">Blog (isr)</Link>
        <Link href="/dashboard" data-testid="nav-dashboard">Dashboard (dynamic)</Link>
        <Link href="/login" data-testid="nav-login">Login</Link>
      </nav>
    </header>

    <p class="mb-4 rounded bg-indigo-50 p-2 text-sm" data-testid="island">
      Island /api/me:
      <span v-if="island.loading.value">loading…</span>
      <span v-else-if="island.data.value?.user">Hello, {{ island.data.value.user.name }}
        <button class="ml-2 underline" data-testid="logout" @click="logout">Sign out</button>
      </span>
      <span v-else>guest</span>
    </p>

    <main><slot /></main>

    <NetworkPanel />

    <section class="mt-4 rounded border bg-white p-3 text-xs">
      <div><b>component:</b> <span data-testid="component">{{ page.component }}</span></div>
      <div><b>url:</b> <span data-testid="url">{{ page.url }}</span></div>
      <div><b>received props:</b> <span data-testid="prop-keys">{{ Object.keys(page.props).sort().join(', ') }}</span></div>
      <pre data-testid="shared-props">{{ JSON.stringify(sharedProps, null, 1) }}</pre>
    </section>
  </div>
</template>
