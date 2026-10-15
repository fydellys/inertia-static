// Islands that load when they become visible (`when: "visible"` / <StaticIsland when="visible">).
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { act, createElement } from 'react'
import { createRoot } from 'react-dom/client'
import { renderToString as reactToString } from 'react-dom/server'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createSSRApp, defineComponent, h } from 'vue'
import { renderToString as vueToString } from 'vue/server-renderer'
import { StaticIsland as ReactIsland } from '../src/react'
import { StaticIsland as VueIsland, useStaticIsland } from '../src/vue'

;(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true

/** IntersectionObserver stand-in: `show()` makes every observed element visible. */
class FakeObserver {
  static instances: FakeObserver[] = []
  elements: Element[] = []
  constructor(
    public callback: IntersectionObserverCallback,
    public options?: IntersectionObserverInit,
  ) {
    FakeObserver.instances.push(this)
  }
  observe(element: Element) {
    this.elements.push(element)
  }
  disconnect() {
    this.elements = []
  }
  static show() {
    for (const observer of FakeObserver.instances) {
      if (observer.elements.length > 0) {
        observer.callback(observer.elements.map((target) => ({ target, isIntersecting: true })) as never, observer as never)
      }
    }
  }
}

let fetch: ReturnType<typeof vi.fn>

beforeEach(() => {
  FakeObserver.instances = []
  vi.stubGlobal('IntersectionObserver', FakeObserver)
  fetch = vi.fn(async () => new Response(JSON.stringify({ orders: 3 }), { status: 200 }))
  vi.stubGlobal('fetch', fetch)
})

afterEach(() => vi.unstubAllGlobals())
enableAutoUnmount(afterEach)

describe('visible island (Vue)', () => {
  const Orders = defineComponent({
    setup: () => () =>
      h(
        VueIsland,
        { url: '/api/me/orders', when: 'visible', buffer: 200 },
        {
          default: ({ data }: { data: { orders: number } }) => h('span', `orders: ${data.orders}`),
          fallback: () => h('span', 'loading…'),
        },
      ),
  })

  it('fetches only when the element becomes visible', async () => {
    const wrapper = mount(Orders)
    await flushPromises()

    expect(fetch).not.toHaveBeenCalled()
    expect(wrapper.text()).toBe('loading…')
    expect(FakeObserver.instances.at(-1)!.options?.rootMargin).toBe('200px')

    FakeObserver.show()
    await flushPromises()

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch).toHaveBeenCalledWith('/api/me/orders', expect.objectContaining({ credentials: 'same-origin' }))
    expect(wrapper.text()).toBe('orders: 3')

    FakeObserver.show()
    await flushPromises()
    expect(fetch).toHaveBeenCalledTimes(1) // once
  })

  it('composable: binds to the element through `target`', async () => {
    const Probe = defineComponent({
      setup() {
        const { data, target } = useStaticIsland<{ orders: number }>('/api/me/orders', { when: 'visible' })

        return () => h('div', { ref: target }, data.value ? `orders: ${data.value.orders}` : 'later')
      },
    })
    const wrapper = mount(Probe)
    await flushPromises()
    expect(fetch).not.toHaveBeenCalled()

    FakeObserver.show()
    await flushPromises()
    expect(wrapper.text()).toBe('orders: 3')
  })

  it('loads right away without IntersectionObserver', async () => {
    vi.stubGlobal('IntersectionObserver', undefined)
    const wrapper = mount(Orders)
    await flushPromises()

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toBe('orders: 3')
  })

  it('a sign in / form before it is visible does not load it; after it loaded, it reloads', async () => {
    mount(Orders)
    await flushPromises()
    const mutation = () => {
      const visit = { method: 'post', id: String(Math.random()) }
      document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit } }))
      document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit } }))
    }

    mutation()
    await flushPromises()
    expect(fetch).not.toHaveBeenCalled()

    FakeObserver.show()
    await flushPromises()
    mutation()
    await flushPromises()
    expect(fetch).toHaveBeenCalledTimes(2)
  })

  it('never runs during SSR: the fallback is the cached HTML', async () => {
    const html = await vueToString(createSSRApp(Orders))

    expect(html).toBe('<div><span>loading…</span></div>')
    expect(fetch).not.toHaveBeenCalled()
  })
})

describe('visible island (React)', () => {
  const Orders = () =>
    createElement(ReactIsland<{ orders: number }>, {
      url: '/api/me/orders',
      when: 'visible',
      fallback: createElement('span', null, 'loading…'),
      children: ({ data }) => createElement('span', null, `orders: ${data!.orders}`),
    })

  it('fetches only when the element becomes visible', async () => {
    const element = document.createElement('div')
    const root = createRoot(element)
    await act(async () => root.render(createElement(Orders)))

    expect(fetch).not.toHaveBeenCalled()
    expect(element.textContent).toBe('loading…')

    await act(async () => FakeObserver.show())
    await act(async () => {})

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(element.textContent).toBe('orders: 3')
    root.unmount()
  })

  it('never runs during SSR: the fallback is the cached HTML', () => {
    expect(reactToString(createElement(Orders))).toBe('<div><span>loading…</span></div>')
    expect(fetch).not.toHaveBeenCalled()
  })
})
