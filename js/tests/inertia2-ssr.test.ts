// @vitest-environment node
import { describe, expect, it } from 'vitest'
import { createSSRApp, h, version } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { ssrRender } from '../src/inertia2/adapter'

// What @inertiajs/vue3 2.x does in SSR: the page app is rendered inside the root
// element's innerHTML (not awaited), then the root is rendered and awaited.
async function inertia2Ssr(render: (app: never) => unknown): Promise<string> {
  const page = createSSRApp({ render: () => h('h1', 'About') })
  const root = createSSRApp({ render: () => h('div', { id: 'app', innerHTML: render(page as never) }) })

  return (await render(root as never)) as string
}

describe('ssrRender (Inertia 2 SSR)', () => {
  it(`breaks with plain renderToString on Vue ${version}`, async () => {
    const html = await inertia2Ssr(renderToString as never)

    // Vue 3.5.44+ stringifies the pending promise; older releases awaited it.
    const [major, minor, patch] = version.split('.').map(Number) as [number, number, number]
    const stringifies = major > 3 || (major === 3 && (minor > 5 || (minor === 5 && patch >= 44)))

    expect(html).toBe(stringifies ? '<div id="app">[object Promise]</div>' : '<div id="app"><h1>About</h1></div>')
  })

  it('renders the page on every Vue release', async () => {
    expect(await inertia2Ssr(ssrRender(renderToString as never))).toBe('<div id="app"><h1>About</h1></div>')
  })
})
