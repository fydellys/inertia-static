import { act, createElement } from 'react'
import { createRoot } from 'react-dom/client'
import { renderToString } from 'react-dom/server'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { useStaticIsland, type StaticIsland } from '../src/react'

// React's act() needs this flag outside a test renderer.
;(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true

function mount(url: string, options = {}) {
  let island: StaticIsland<{ name: string }> | null = null
  const Probe = () => {
    island = useStaticIsland<{ name: string }>(url, options)
    return createElement('span', null, island.data?.name ?? 'guest')
  }
  const element = document.createElement('div')
  const root = createRoot(element)

  return { element, root, island: () => island!, render: () => act(() => root.render(createElement(Probe))) }
}

afterEach(() => vi.unstubAllGlobals())

describe('useStaticIsland (React)', () => {
  it('loads the island in the browser, with cookies', async () => {
    const fetch = vi.fn(async () => new Response(JSON.stringify({ name: 'Alice' }), { status: 200 }))
    vi.stubGlobal('fetch', fetch)
    const view = mount('/api/me')

    await view.render()
    await act(async () => {})

    expect(view.element.textContent).toBe('Alice')
    expect(fetch).toHaveBeenCalledWith('/api/me', expect.objectContaining({ credentials: 'same-origin' }))
    view.root.unmount()
  })

  it('never runs during SSR (the cached HTML is the same for everyone)', () => {
    const fetch = vi.fn()
    vi.stubGlobal('fetch', fetch)
    const Probe = () => createElement('span', null, useStaticIsland<{ name: string }>('/api/me').data?.name ?? 'guest')

    expect(renderToString(createElement(Probe))).toBe('<span>guest</span>')
    expect(fetch).not.toHaveBeenCalled()
  })

  it('reloads after a non-GET Inertia visit (sign in, sign out, forms)', async () => {
    let name = 'guest'
    const fetch = vi.fn(async () => new Response(JSON.stringify({ name }), { status: 200 }))
    vi.stubGlobal('fetch', fetch)
    const view = mount('/api/me')
    await view.render()
    await act(async () => {})

    name = 'Bob'
    const visit = { method: 'post', id: 'v1' }
    await act(async () => {
      document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit } }))
      document.dispatchEvent(new CustomEvent('inertia:finish', { detail: { visit } }))
    })

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(view.element.textContent).toBe('Bob')
    view.root.unmount()
  })

  it('reports errors without throwing', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('', { status: 500 })))
    const view = mount('/api/me')

    await view.render()
    await act(async () => {})

    expect(String(view.island().error)).toContain('failed with status 500')
    expect(view.element.textContent).toBe('guest')
    view.root.unmount()
  })
})
