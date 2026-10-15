import { createInertiaApp, router } from '@inertiajs/vue3'
import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, effectScope, h, type DefineComponent, type EffectScope } from 'vue'
import { inertiaStatic, useStaticIsland } from '../src/vue'
import { fakeClient, json, manifest, page } from './helpers'

const origin = 'https://example.com'

const scopes: EffectScope[] = []

/** Runs an island inside an effect scope that is stopped after the test. */
function inScope<T>(fn: () => T): T {
  const scope = effectScope()
  scopes.push(scope)

  return scope.run(fn)!
}

afterEach(() => scopes.splice(0).forEach((scope) => scope.stop()))

const Page = (name: string) =>
  defineComponent({ props: { title: String }, setup: (props) => () => h('h1', `${name}:${props.title}`) })

const pages: Record<string, DefineComponent> = {
  Home: Page('Home') as DefineComponent,
  'Blog/Show': Page('Show') as DefineComponent,
  Dashboard: Page('Dashboard') as DefineComponent,
}

describe('createInertiaApp integration (Vue)', () => {
  it('navigates between static pages through the JSON URL and dynamic pages normally', async () => {
    window.history.replaceState({}, '', '/')
    document.body.innerHTML = '<div id="app"></div>'

    const { client, calls } = fakeClient((config) => {
      if (config.url === `${origin}/__inertia/v1/blog/hello.json`) {
        // A static host / CDN: no X-Inertia header on the response.
        return json(page('Blog/Show', '/blog/hello', { title: 'Hello' }))
      }

      return json(page('Dashboard', '/dashboard', { title: 'Private' }), { 'x-inertia': 'true' })
    })

    await createInertiaApp({
      page: page('Home', '/', { title: 'Home' }),
      resolve: (name) => pages[name]!,
      setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el!),
      http: inertiaStatic({ manifest, client }),
    })

    await router.visit('/blog/hello')
    await vi.waitFor(() => expect(document.body.textContent).toContain('Show:Hello'))

    expect(calls[0]!.url).toBe(`${origin}/__inertia/v1/blog/hello.json`)
    expect(window.location.pathname).toBe('/blog/hello')

    await router.visit('/dashboard')
    await vi.waitFor(() => expect(document.body.textContent).toContain('Dashboard:Private'))

    expect(calls[1]!.url).toBe(`${origin}/dashboard`)
  })
})

describe('useStaticIsland', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('loads user data in the browser after mount', async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ name: 'Alice' }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(
      defineComponent({
        setup() {
          const { data, loading } = useStaticIsland<{ name: string }>('/api/me')

          return () => h('span', loading.value ? 'loading' : (data.value?.name ?? 'guest'))
        },
      }),
    )

    await flushPromises()

    expect(wrapper.text()).toBe('Alice')
    expect(fetchMock).toHaveBeenCalledWith('/api/me', expect.objectContaining({ credentials: 'same-origin' }))

    wrapper.unmount()
  })

  it('exposes errors and keeps the initial value', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('', { status: 401 })))

    const island = inScope(() => useStaticIsland('/api/me', { initial: { name: 'guest' } }))
    await flushPromises()

    expect(island.data.value).toEqual({ name: 'guest' })
    expect(island.error.value).toBeInstanceOf(Error)
  })

  it('can be loaded lazily', async () => {
    const fetchMock = vi.fn(async () => new Response('{"n":1}', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const island = inScope(() => useStaticIsland('/api/cart', { immediate: false }))
    expect(fetchMock).not.toHaveBeenCalled()

    expect(await island.load()).toEqual({ n: 1 })
  })
})

describe('useStaticIsland after mutations', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('reloads after a non-GET visit finishes (e.g. logout to the same page)', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response('{"user":"alice"}', { status: 200 }))
      .mockResolvedValueOnce(new Response('{"user":null}', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const island = inScope(() => useStaticIsland<{ user: string | null }>('/api/me'))
    await flushPromises()
    expect(island.data.value).toEqual({ user: 'alice' })

    const visit = { id: 'v1', method: 'post' }
    document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit } }))
    document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit } }))
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(island.data.value).toEqual({ user: null })
  })

  it('ignores GET visits', async () => {
    const fetchMock = vi.fn(async () => new Response('{}', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    inScope(() => useStaticIsland('/api/me'))
    await flushPromises()

    const visit = { id: 'v2', method: 'get' }
    document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit } }))
    document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit } }))
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})

describe('useStaticIsland stale data', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('drops the previous visitor data when a reload fails (401 after logout)', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response('{"user":"alice"}', { status: 200 }))
      .mockResolvedValueOnce(new Response('', { status: 401 }))
    vi.stubGlobal('fetch', fetchMock)

    const island = inScope(() => useStaticIsland<{ user: string | null }>('/api/me', { initial: { user: null } }))
    await flushPromises()
    expect(island.data.value).toEqual({ user: 'alice' })

    await island.load()

    expect(island.data.value).toEqual({ user: null })
    expect(island.error.value).toBeInstanceOf(Error)
  })

  it('keeps the newest response when an older one arrives last', async () => {
    let resolveOld: (response: Response) => void = () => {}
    const fetchMock = vi
      .fn()
      .mockReturnValueOnce(new Promise<Response>((resolve) => (resolveOld = resolve)))
      .mockResolvedValueOnce(new Response('{"user":null}', { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const island = inScope(() => useStaticIsland<{ user: string | null }>('/api/me', { immediate: false }))
    const old = island.load()
    await island.load()
    resolveOld(new Response('{"user":"alice"}', { status: 200 }))
    await old

    expect(island.data.value).toEqual({ user: null })
    expect(island.loading.value).toBe(false)
  })
})
