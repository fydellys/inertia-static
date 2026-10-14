<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { network } from '../devtools/recorder'

interface OriginStats {
  static: number
  dynamic: number
  log: { at: string; type: string; method: string; uri: string; status: number; xInertia: boolean }[]
}

const mounted = ref(false)
const initial = ref<{ status: number | string; cdn: string; origin: string }>({ status: '?', cdn: '?', origin: '?' })
const origin = ref<OriginStats | null>(null)

async function refreshOrigin() {
  const response = await fetch('/__origin/stats', { cache: 'no-store' })
  origin.value = await response.json()
}

async function resetOrigin() {
  await fetch('/__origin/reset', { cache: 'no-store' })
  network.requests.splice(0)
  network.errors.splice(0)
  await refreshOrigin()
}

const onFinish = () => setTimeout(refreshOrigin, 150)

onMounted(() => {
  mounted.value = true
  const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming & { responseStatus?: number }
  const timing = (name: string) => nav?.serverTiming?.find((entry) => entry.name === name)?.description ?? '-'
  const cdn = timing('cdn') !== '-' ? timing('cdn') : timing('cfCacheStatus') // nginx demo / Cloudflare
  initial.value = { status: nav?.responseStatus ?? '?', cdn, origin: timing('origin') }
  refreshOrigin()
  document.addEventListener('inertia:finish', onFinish)
})

onUnmounted(() => document.removeEventListener('inertia:finish', onFinish))
</script>

<template>
  <section v-if="mounted" class="mt-6 rounded border border-slate-300 bg-white p-3 text-xs" data-testid="network-panel">
    <h2 class="mb-2 text-sm font-bold">Network panel (demo)</h2>

    <p data-testid="initial-load">
      Initial load (HTML): status <b>{{ initial.status }}</b> · CDN <b>{{ initial.cdn }}</b>
    </p>

    <p class="my-1" data-testid="origin-hits">
      ORIGIN hits — static pages: <b data-testid="origin-static">{{ origin?.static ?? '…' }}</b>
      · dynamic: <b data-testid="origin-dynamic">{{ origin?.dynamic ?? '…' }}</b>
      <button class="ml-2 rounded border px-1" data-testid="reset-origin" @click="resetOrigin">reset</button>
    </p>

    <p v-if="network.errors.length" class="text-red-700" data-testid="inertia-errors">Inertia errors: {{ network.errors.join(' | ') }}</p>
    <p v-else class="text-green-700" data-testid="inertia-errors">Inertia errors: none</p>

    <table class="mt-2 w-full text-left" data-testid="requests">
      <thead>
        <tr><th>#</th><th>method</th><th>URL on the wire</th><th>status</th><th>CDN</th><th>X-Inertia-Static (origin decision)</th><th>x-inertia on the wire</th><th>origin?</th><th>browser</th><th>CF→origin ms</th><th>ms</th></tr>
      </thead>
      <tbody>
        <tr v-for="r in network.requests.slice(0, 12)" :key="r.id" :class="r.cache === 'HIT' ? 'text-green-700' : ''">
          <td>{{ r.id }}</td><td>{{ r.method }}</td><td class="break-all">{{ r.url }}</td><td>{{ r.status }}</td>
          <td>{{ r.cache }}</td><td>{{ r.inertiaStatic }}</td><td>{{ r.xInertiaOnWire ? 'yes' : 'NO' }}</td>
          <td>{{ r.originHit ? 'yes' : 'no' }}</td><td data-testid="browser-cache">{{ r.browser }}</td><td>{{ r.originMs }}</td><td>{{ r.ms }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
