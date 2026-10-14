import { HttpResponseError, type HttpClient, type HttpRequestConfig, type HttpResponse, type Page } from '@inertiajs/core'
import { vi } from 'vitest'
import type { StaticManifest } from '../src'

export const manifest: StaticManifest = {
  prefix: '/__inertia',
  routes: [
    { name: 'home', pattern: '^/$', mode: 'static' },
    { name: 'blog.index', pattern: '^/blog/?$', mode: 'isr', query: ['page'] },
    { name: 'blog.show', pattern: '^/blog/([^/]+)/?$', mode: 'isr' },
  ],
}

export const page = (component: string, url: string, props: Record<string, unknown> = {}): Page =>
  ({
    component,
    url,
    version: 'v1',
    props: { errors: {}, ...props },
    flash: {},
    rescuedProps: [],
    rememberedState: {},
  }) as unknown as Page

export type Handler = (config: HttpRequestConfig) => HttpResponse | Promise<HttpResponse>

export function fakeClient(handler: Handler) {
  const calls: HttpRequestConfig[] = []
  const client: HttpClient = {
    request: vi.fn(async (config: HttpRequestConfig) => {
      calls.push(config)
      const response = await handler(config)

      if (response.status >= 400) {
        throw new HttpResponseError(`Request failed with status ${response.status}`, response, config.url)
      }

      return response
    }),
  }

  return { client, calls }
}

export const json = (data: unknown, headers: Record<string, string> = {}): HttpResponse => ({
  status: 200,
  data: JSON.stringify(data),
  headers: { 'content-type': 'application/json', ...headers },
})

export const visit = (url: string, headers: Record<string, unknown> = {}, method = 'get'): HttpRequestConfig =>
  ({
    method,
    url,
    data: {},
    headers: {
      Accept: 'text/html, application/xhtml+xml',
      'X-Requested-With': 'XMLHttpRequest',
      'X-Inertia': true,
      'X-Inertia-Version': 'v1',
      ...headers,
    },
  }) as HttpRequestConfig
