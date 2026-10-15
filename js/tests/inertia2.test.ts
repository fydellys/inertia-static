// The real Inertia 2 router (@inertiajs/core 2.x) driving the axios adapter.
import { router } from '@inertiajs/core-v2'
import axios from 'axios'
import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { installInertiaStatic } from '../src/inertia2'
import { manifest, page } from './helpers'

type Reply = { status: number; data: string; headers: Record<string, string> }

const origin = 'https://example.com'
const requests: { url: string; headers: Record<string, unknown> }[] = []
let reply: (url: string) => Reply
let current: { component: string; url: string; props: Record<string, unknown> } | null = null
let uninstall: () => void

const pageJson = (component: string, url: string, props: Record<string, unknown> = {}, headers: Record<string, string> = {}): Reply => ({
  status: 200,
  data: JSON.stringify(page(component, url, props)),
  headers: { 'content-type': 'application/json', ...headers },
})

// Stands in for axios' XHR adapter: what the network (the CDN) answers.
const network = vi.fn(async (config: { url: string; headers: Record<string, unknown> }) => {
  const url = new URL(config.url, window.location.href).href
  requests.push({ url, headers: { ...config.headers } })
  const response = { ...reply(url), statusText: '', config, request: {} }

  if (response.status >= 400) {
    throw Object.assign(new Error(`Request failed with status ${response.status}`), { response })
  }

  return response
})

const visit = (url: string, options: Record<string, unknown> = {}) =>
  new Promise<void>((resolve) => router.visit(url, { ...options, onFinish: () => resolve() }))

beforeAll(() => {
  axios.defaults.adapter = network as never
  uninstall = installInertiaStatic(axios as never, { manifest })
  router.init({
    initialPage: page('Home', '/') as never,
    resolveComponent: async (name: string) => ({ name }),
    swapComponent: async ({ page: next }) => {
      current = next as unknown as typeof current
    },
  })
})

afterAll(() => uninstall())

beforeEach(() => {
  requests.length = 0
  reply = (url) => (url.includes('/blog') ? pageJson('Blog/Index', '/blog') : pageJson('Home', '/'))
})

describe('Inertia 2 (axios adapter)', () => {
  it('sends a visit to a static page to its cacheable JSON URL, never the HTML URL', async () => {
    // A CDN that strips X-Inertia: the adapter restores it.
    await visit('/blog')

    expect(requests.map((r) => r.url)).toEqual([`${origin}/__inertia/v1/blog.json`])
    expect(current).toMatchObject({ component: 'Blog/Index', url: '/blog' })
  })

  it('sends router.reload() to the live endpoint', async () => {
    await visit('/blog')
    requests.length = 0

    await new Promise<void>((resolve) => router.reload({ onFinish: () => resolve() }))

    expect(requests.map((r) => r.url)).toEqual([`${origin}/__inertia/live/v1/blog.json`])
  })

  it('sends partial reloads to the live endpoint', async () => {
    await visit('/blog')
    requests.length = 0

    await new Promise<void>((resolve) => router.reload({ only: ['posts'], onFinish: () => resolve() }))

    expect(requests[0]?.url).toBe(`${origin}/__inertia/live/v1/blog.json`)
    expect(requests[0]?.headers['X-Inertia-Partial-Data']).toBe('posts')
  })

  it('leaves dynamic pages and non-GET requests alone', async () => {
    reply = () => pageJson('Dashboard', '/dashboard', {}, { 'x-inertia': 'true' })

    await visit('/dashboard')
    await visit('/blog', { method: 'post' })

    expect(requests.map((r) => r.url)).toEqual([`${origin}/dashboard`, `${origin}/blog`])
  })

  it('answers 409 + X-Inertia-Location when the static JSON is missing (Inertia then reloads the page)', async () => {
    reply = () => ({ status: 404, data: 'Not found', headers: { 'content-type': 'text/html' } })

    const response = await axios.get('/blog', { headers: { 'X-Inertia': true, 'X-Inertia-Version': 'v1' } })

    expect(requests.map((r) => r.url)).toEqual([`${origin}/__inertia/v1/blog.json`])
    expect(response.status).toBe(409)
    expect(response.headers['x-inertia-location']).toBe(`${origin}/blog`)
  })

  it('restores x-inertia on a page JSON reached through a redirect', async () => {
    reply = () => pageJson('Home', '/')

    const response = await axios.post('/newsletter', {}, { headers: { 'X-Inertia': true } })

    expect(response.headers['x-inertia']).toBe('true')
  })

  it('fetches the CSRF cookie before a form submission when the manifest asks for it (Laravel 12)', async () => {
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    const fetch = vi.fn(async () => {
      document.cookie = 'XSRF-TOKEN=tok'
      return new Response(null, { status: 204 })
    })
    vi.stubGlobal('fetch', fetch)
    const local = axios.create()
    local.defaults.adapter = network as never
    installInertiaStatic(local as never, { manifest: { ...manifest, csrfCookieUrl: '/__inertia/csrf' } })
    reply = () => pageJson('Home', '/', {}, { 'x-inertia': 'true' })

    await local.post('/newsletter', { email: 'a@b.co' }, { headers: { 'X-Inertia': true } })

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(requests).toHaveLength(1) // axios then sends X-XSRF-TOKEN from the cookie itself
    vi.unstubAllGlobals()
  })

  it('does not install twice', () => {
    const adapter = axios.defaults.adapter

    installInertiaStatic(axios as never, { manifest })

    expect(axios.defaults.adapter).toBe(adapter)
  })
})
