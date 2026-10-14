<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { useJsonForm } from '@inertia-version'
import { ref } from 'vue'

// Two ways of submitting a form, to compare them on static and dynamic pages:
// A. useForm (an Inertia visit): POST /newsletter → return back() (redirect + flash + session errors)
// B. useHttp (plain JSON request): POST /api/newsletter → JSON, 422 validation errors in the response
const page = usePage()

const form = useForm({ email: '' })
const resultA = ref('')

function submitA() {
  resultA.value = ''
  form.post('/newsletter', {
    preserveScroll: true,
    onSuccess: () => (resultA.value = `ok, now on ${page.url}`),
    onError: () => (resultA.value = 'validation error'),
  })
}

const http = useJsonForm({ email: '' })
const resultB = ref('')

async function submitB() {
  resultB.value = ''
  await http.post('/api/newsletter', {
    onSuccess: (data: { message: string }) => (resultB.value = data.message),
    onError: () => (resultB.value = 'validation error'),
  })
}
</script>

<template>
  <section class="mt-6 grid gap-4 rounded border bg-white p-3 text-sm md:grid-cols-2">
    <form data-testid="form-a" @submit.prevent="submitA">
      <h3 class="font-bold">A. useForm → return back()</h3>
      <input v-model="form.email" class="w-full border p-1" placeholder="email" data-testid="a-email" />
      <p class="text-red-600" data-testid="a-error">{{ form.errors.email ?? '' }}</p>
      <p class="text-green-700" data-testid="a-flash">{{ ($page as any).flash?.newsletter ?? '' }}</p>
      <p data-testid="a-result">{{ resultA }}</p>
      <button class="mt-1 rounded bg-slate-800 px-3 py-1 text-white" data-testid="a-submit">Subscribe</button>
    </form>

    <form data-testid="form-b" @submit.prevent="submitB">
      <h3 class="font-bold">B. useHttp → JSON</h3>
      <input v-model="http.email" class="w-full border p-1" placeholder="email" data-testid="b-email" />
      <p class="text-red-600" data-testid="b-error">{{ http.errors.email ?? '' }}</p>
      <p class="text-green-700" data-testid="b-result">{{ resultB }}</p>
      <button class="mt-1 rounded bg-slate-800 px-3 py-1 text-white" data-testid="b-submit">Subscribe</button>
    </form>
  </section>
</template>
