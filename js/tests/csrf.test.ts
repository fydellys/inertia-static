import { afterEach, expect, it, vi } from 'vitest'
import { ensureCsrfCookie } from '../src'

afterEach(() => vi.unstubAllGlobals())

it('fetches the CSRF cookie once for concurrent calls', async () => {
  const fetchMock = vi.fn(async () => new Response(null, { status: 204 }))
  vi.stubGlobal('fetch', fetchMock)

  await Promise.all([ensureCsrfCookie(), ensureCsrfCookie()])

  expect(fetchMock).toHaveBeenCalledTimes(1)
  expect(fetchMock).toHaveBeenCalledWith('/sanctum/csrf-cookie', expect.objectContaining({ credentials: 'same-origin' }))
})

it('skips the request when the cookie already exists', async () => {
  const fetchMock = vi.fn()
  vi.stubGlobal('fetch', fetchMock)
  document.cookie = 'XSRF-TOKEN=abc'

  await ensureCsrfCookie()

  expect(fetchMock).not.toHaveBeenCalled()
})
