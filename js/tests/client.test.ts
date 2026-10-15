import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createStaticHttpClient } from '../src'
import { fakeClient, json, manifest, page, visit } from './helpers'
import { encodePath, jsonPath } from '../src/urls'

const origin = 'https://example.com'

beforeEach(() => {
  window.history.replaceState({}, '', '/')
})

describe('rewrite decisions', () => {
  const client = createStaticHttpClient({ manifest })

  it.each([
    ['/blog', `${origin}/__inertia/v1/blog.json`],
    ['/blog/', `${origin}/__inertia/v1/blog.json`],
    ['/blog/hello-world', `${origin}/__inertia/v1/blog/hello-world.json`],
    ['/blog?page=2', `${origin}/__inertia/v1/blog.json?page=2`],
  ])('rewrites static route %s', (path, expected) => {
    expect(client.decide(visit(origin + path))).toMatchObject({ rewrite: true, url: expected })
  })

  it('maps the root page to index.json', () => {
    window.history.replaceState({}, '', '/blog')

    expect(client.decide(visit(origin + '/')).url).toBe(`${origin}/__inertia/v1/index.json`)
  })

  it('uses "_" when the page has no version', () => {
    const config = visit(origin + '/blog')
    delete config.headers!['X-Inertia-Version']

    expect(client.decide(config).url).toBe(`${origin}/__inertia/_/blog.json`)
  })

  it.each([
    ['dynamic route', visit(origin + '/dashboard'), 'not-static'],
    ['POST', visit(origin + '/blog', {}, 'post'), 'method'],
    ['cross origin', visit('https://other.test/blog'), 'cross-origin'],
    ['useHttp request', { method: 'get', url: origin + '/blog', headers: {} }, 'not-a-visit'],
  ])('does not rewrite: %s', (_, config, reason) => {
    expect(client.decide(config as never)).toMatchObject({ rewrite: false, reason })
  })

  // Never the HTML URL (inertia#1923): fresh data comes from the never-cached live endpoint.
  it.each([
    ['partial reload', visit(origin + '/blog', { 'X-Inertia-Partial-Component': 'Blog', 'X-Inertia-Partial-Data': 'posts' }), 'live:x-inertia-partial-component', '/blog.json'],
    ['deferred props', visit(origin + '/blog', { 'X-Inertia-Partial-Data': 'comments' }), 'live:x-inertia-partial-data', '/blog.json'],
    ['reset', visit(origin + '/blog', { 'X-Inertia-Reset': 'posts' }), 'live:x-inertia-reset', '/blog.json'],
    ['explicit bypass', visit(origin + '/blog', { 'X-Inertia-Static-Bypass': '1' }), 'live:x-inertia-static-bypass', '/blog.json'],
    ['non whitelisted query', visit(origin + '/blog?utm_source=x'), 'live:query', '/blog.json?utm_source=x'],
  ])('sends to the live endpoint: %s', (_, config, reason, path) => {
    expect(client.decide(config as never)).toMatchObject({
      rewrite: true,
      cacheable: false,
      reason,
      url: `${origin}/__inertia/live/v1${path}`,
    })
  })

  it('sends router.reload() / polling (Cache-Control: no-cache) to the live endpoint, never to the HTML URL', () => {
    window.history.replaceState({}, '', '/blog?page=2')

    expect(client.decide(visit(origin + '/blog?page=2', { 'Cache-Control': 'no-cache' }))).toMatchObject({
      rewrite: true,
      cacheable: false,
      reason: 'live:reload',
      url: `${origin}/__inertia/live/v1/blog.json?page=2`,
    })
  })

  it('sorts whitelisted query parameters', () => {
    const sorted = createStaticHttpClient({
      manifest: { prefix: '/__inertia', routes: [{ pattern: '^/search/?$', mode: 'isr', query: ['q', 'page'] }] },
    })

    expect(sorted.decide(visit(origin + '/search?q=a%20b&page=2')).url).toBe(
      `${origin}/__inertia/v1/search.json?page=2&q=a%20b`,
    )
  })

  it('respects the trailing slash policy', () => {
    const always = createStaticHttpClient({ manifest, trailingSlash: 'always' })

    expect(always.decide(visit(origin + '/blog')).url).toBe(`${origin}/__inertia/v1/blog.json`)
  })
})

describe('requests', () => {
  it('sends static visits to the JSON URL and injects the Inertia headers', async () => {
    const { client, calls } = fakeClient(() => json(page('Blog/Index', '/blog')))
    const http = createStaticHttpClient({ manifest, client })

    const response = await http.request(visit(origin + '/blog'))

    expect(calls[0]!.url).toBe(`${origin}/__inertia/v1/blog.json`)
    expect(response.headers['x-inertia']).toBe('true')
    expect(JSON.parse(response.data).component).toBe('Blog/Index')
  })

  it('serves a click on the link of the current page from the cache (not live)', () => {
    window.history.replaceState({}, '', '/blog')

    expect(createStaticHttpClient({ manifest }).decide(visit(origin + '/blog'))).toMatchObject({
      rewrite: true,
      cacheable: true,
      url: `${origin}/__inertia/v1/blog.json`,
    })
  })

  it('sends a reload of the current static page to the live endpoint and injects the Inertia headers', async () => {
    window.history.replaceState({}, '', '/about')
    const { client, calls } = fakeClient(() => json(page('About', '/about')))
    const http = createStaticHttpClient({
      manifest: { prefix: '/__inertia', routes: [{ pattern: '^/about/?$', mode: 'static' }] },
      client,
    })

    const response = await http.request(visit(origin + '/about', { 'Cache-Control': 'no-cache' }))

    expect(calls[0]!.url).toBe(`${origin}/__inertia/live/v1/about.json`)
    expect(response.headers['x-inertia']).toBe('true')
  })

  it('never requests the HTML URL of a static page', async () => {
    window.history.replaceState({}, '', '/blog')
    const { client, calls } = fakeClient(() => json(page('Blog/Index', '/blog')))
    const http = createStaticHttpClient({ manifest, client })

    await http.request(visit(origin + '/blog', { 'Cache-Control': 'no-cache' }))
    await http.request(visit(origin + '/blog', { 'X-Inertia-Partial-Component': 'Blog/Index', 'X-Inertia-Partial-Data': 'posts' }))
    await http.request(visit(origin + '/blog?utm_source=x'))

    expect(calls.map((c) => new URL(c.url).pathname)).toEqual([
      '/__inertia/live/v1/blog.json',
      '/__inertia/live/v1/blog.json',
      '/__inertia/live/v1/blog.json',
    ])
  })

  it('restores x-inertia on a page JSON reached through a redirect (return back() to a static page)', async () => {
    // POST /newsletter → 302 → /__inertia/live/v1/about.json, followed inside the XHR; a proxy stripped x-inertia
    const { client } = fakeClient(() => json(page('About', '/about')))
    const http = createStaticHttpClient({ manifest, client })

    const response = await http.request(visit(origin + '/newsletter', {}, 'post'))

    expect(response.headers['x-inertia']).toBe('true')
  })

  it('does not invent x-inertia for non-page JSON or non-visits', async () => {
    const { client } = fakeClient(() => json({ message: 'ok' }))
    const http = createStaticHttpClient({ manifest, client })

    expect((await http.request(visit(origin + '/newsletter', {}, 'post'))).headers['x-inertia']).toBeUndefined()

    const { client: other } = fakeClient(() => json(page('About', '/about')))
    const plain = { method: 'post', url: origin + '/api/newsletter', headers: {} } as never
    expect((await createStaticHttpClient({ manifest, client: other }).request(plain)).headers['x-inertia']).toBeUndefined()
  })

  it('sends every static visit to the live endpoint in preview mode', () => {
    window.history.replaceState({}, '', '/')
    document.cookie = 'inertia_static_preview=123.abc'
    const http = createStaticHttpClient({ manifest })

    expect(http.decide(visit(origin + '/blog'))).toMatchObject({ rewrite: true, cacheable: false, reason: 'live:preview' })

    document.cookie = 'inertia_static_preview=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    expect(http.decide(visit(origin + '/blog'))).toMatchObject({ cacheable: true })
  })

  it('passes other requests through untouched', async () => {
    const { client, calls } = fakeClient(() => json(page('Dashboard', '/dashboard'), { 'x-inertia': 'true' }))
    const http = createStaticHttpClient({ manifest, client })
    const config = visit(origin + '/dashboard')

    await http.request(config)

    expect(calls[0]).toBe(config)
  })

  it('falls back to a full reload (409 + location) when the static JSON is missing', async () => {
    const { client, calls } = fakeClient(() => ({ status: 404, data: 'Not found', headers: {} }))
    const http = createStaticHttpClient({ manifest, client })

    const response = await http.request(visit(origin + '/blog/missing'))

    expect(calls).toHaveLength(1)
    expect(response.status).toBe(409)
    expect(response.headers['x-inertia-location']).toBe(`${origin}/blog/missing`)
  })

  it('treats an HTML fallback page (SPA hosts) as missing', async () => {
    const { client } = fakeClient(() => ({ status: 200, data: '<!doctype html>', headers: { 'content-type': 'text/html' } }))
    const http = createStaticHttpClient({ manifest, client })

    expect((await http.request(visit(origin + '/blog'))).status).toBe(409)
  })

  it('can retry against the origin once instead', async () => {
    const { client, calls } = fakeClient((config) =>
      config.url.includes('/__inertia/')
        ? { status: 404, data: '', headers: {} }
        : json(page('Blog/Index', '/blog'), { 'x-inertia': 'true' }),
    )
    const http = createStaticHttpClient({ manifest, client, onMissing: 'origin' })

    const response = await http.request(visit(origin + '/blog'))

    expect(calls.map((c) => c.url)).toEqual([`${origin}/__inertia/v1/blog.json`, `${origin}/blog`])
    expect(response.headers['x-inertia']).toBe('true')
  })

  it('propagates the 409 of a version mismatch so Inertia reloads', async () => {
    const { client } = fakeClient(() => ({ status: 409, data: '', headers: { 'x-inertia-location': `${origin}/blog` } }))
    const http = createStaticHttpClient({ manifest, client })

    await expect(http.request(visit(origin + '/blog'))).rejects.toMatchObject({ response: { status: 409 } })
  })

  it('propagates server errors', async () => {
    const { client } = fakeClient(() => ({ status: 500, data: '', headers: {} }))
    const http = createStaticHttpClient({ manifest, client })

    await expect(http.request(visit(origin + '/blog'))).rejects.toMatchObject({ response: { status: 500 } })
  })
})

describe('manifest loading', () => {
  it('fetches the manifest once and caches it', async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify(manifest), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    const { client, calls } = fakeClient(() => json(page('Blog/Index', '/blog')))
    const http = createStaticHttpClient({ client })

    await http.request(visit(origin + '/blog'))
    await http.request(visit(origin + '/blog/a'))

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledWith('/__inertia/manifest.json', expect.anything())
    expect(calls.map((c) => c.url)).toEqual([`${origin}/__inertia/v1/blog.json`, `${origin}/__inertia/v1/blog/a.json`])

    vi.unstubAllGlobals()
  })

  it('fails open when the manifest cannot be loaded', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('', { status: 500 })))

    const { client, calls } = fakeClient(() => json(page('Blog/Index', '/blog'), { 'x-inertia': 'true' }))
    await createStaticHttpClient({ client }).request(visit(origin + '/blog'))

    expect(calls[0]!.url).toBe(`${origin}/blog`)

    vi.unstubAllGlobals()
  })
})

describe('CSRF cookie for static pages (Laravel 12)', () => {
  const clearCookie = () => (document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT')

  it('fetches the cookie once before a form submission and sends the token', async () => {
    clearCookie()
    const fetch = vi.fn(async () => {
      document.cookie = 'XSRF-TOKEN=abc%3D'
      return new Response(null, { status: 204 })
    })
    vi.stubGlobal('fetch', fetch)
    const cookieAtRequest: boolean[] = []
    const { client } = fakeClient(() => {
      cookieAtRequest.push(document.cookie.includes('XSRF-TOKEN=abc'))
      return json(page('Home', '/'), { 'x-inertia': 'true' })
    })
    const http = createStaticHttpClient({ manifest: { ...manifest, csrfCookieUrl: '/__inertia/csrf' }, client })

    await http.request(visit(origin + '/newsletter', {}, 'post'))
    await http.request(visit(origin + '/newsletter', {}, 'post'))

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch.mock.calls[0]).toEqual(['/__inertia/csrf', { credentials: 'same-origin' }])
    expect(cookieAtRequest).toEqual([true, true]) // Inertia sends the token from the cookie itself
    vi.unstubAllGlobals()
    clearCookie()
  })

  it('does nothing for GET visits or when the manifest does not ask for it (Laravel 13)', async () => {
    clearCookie()
    const fetch = vi.fn()
    vi.stubGlobal('fetch', fetch)
    const { client, calls } = fakeClient(() => json(page('Home', '/'), { 'x-inertia': 'true' }))

    await createStaticHttpClient({ manifest, client }).request(visit(origin + '/newsletter', {}, 'post'))
    await createStaticHttpClient({ manifest: { ...manifest, csrfCookieUrl: '/__inertia/csrf' }, client }).request(visit(origin + '/dashboard'))

    expect(fetch).not.toHaveBeenCalled()
    expect(calls).toHaveLength(2)
    vi.unstubAllGlobals()
  })
})

describe('encodePath', () => {
  it('spells paths the way the server canonicalizes them (no extra 301)', () => {
    expect(encodePath('/blog/a b')).toBe('/blog/a%20b')
    expect(encodePath('/blog/a%20b')).toBe('/blog/a%20b')
    expect(encodePath('/blog/%68ello')).toBe('/blog/hello')
    expect(encodePath("/@user/x!$&'()+,;=:")).toBe("/@user/x!$&'()+,;=:")
    expect(encodePath('/x*|^')).toBe('/x%2A%7C%5E')
    expect(encodePath('/%e3%81%82')).toBe('/%E3%81%82')
    expect(encodePath('/%2562log')).toBe('/%2562log')
    expect(encodePath('/a%zz')).toBe('/a%25zz')
    expect(jsonPath('/blog/a b', '/__inertia', 'v1')).toBe('/__inertia/v1/blog/a%20b.json')
  })
})
