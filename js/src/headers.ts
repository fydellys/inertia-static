/** Request or response headers: a plain object, Inertia's headers or an AxiosHeaders instance. */
export type HeaderBag = object

export function getHeader(headers: HeaderBag | undefined, name: string): unknown {
  if (!headers) {
    return undefined
  }

  const key = Object.keys(headers).find((header) => header.toLowerCase() === name.toLowerCase())

  return key === undefined ? undefined : (headers as Record<string, unknown>)[key]
}

export function hasHeader(headers: HeaderBag | undefined, name: string): boolean {
  const value = getHeader(headers, name)

  return value !== undefined && value !== null && value !== false && value !== ''
}

/** Headers marking a request that must never be served from the cache. */
export const BYPASS_HEADERS = [
  'X-Inertia-Partial-Component',
  'X-Inertia-Partial-Data',
  'X-Inertia-Partial-Except',
  'X-Inertia-Reset',
  'X-Inertia-Infinite-Scroll-Merge-Intent',
  'X-Inertia-Static-Bypass',
  'Precognition',
]
