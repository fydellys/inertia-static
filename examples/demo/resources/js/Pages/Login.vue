<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import Layout from '../Layouts/Layout.vue'

// Persistent layout: mounted once, kept across visits (the /api/me island loads once).
defineOptions({ layout: Layout })

const form = useForm({ email: 'alice@example.com', password: 'password' })
</script>

<template>
  <Head title="Login" />
  <h1 class="text-2xl font-bold">Login (dynamic)</h1>
  <form class="mt-3 flex flex-col gap-2" @submit.prevent="form.post('/login')">
    <input v-model="form.email" class="border p-1" data-testid="email" />
    <input v-model="form.password" type="password" class="border p-1" data-testid="password" />
    <p v-if="form.errors.email" class="text-red-600">{{ form.errors.email }}</p>
    <button type="submit" class="rounded bg-slate-800 p-2 text-white" data-testid="submit">Sign in</button>
  </form>
</template>
