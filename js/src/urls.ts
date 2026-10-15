export type TrailingSlash = 'never' | 'always' | 'ignore'

/**
 * Canonical query string: only whitelisted keys, sorted, empty values removed.
 * Returns null when the URL carries keys outside the whitelist (the visit then
 * goes to the origin, which redirects to the canonical URL).
 */
export function canonicalQuery(url: URL, whitelist: string[]): string | null {
  const params = [...url.searchParams.entries()].filter(([, value]) => value !== '')

  if (params.some(([key]) => !whitelist.includes(key.replace(/\[.*$/, '')))) {
    return null
  }

  params.sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))

  return params.length === 0 ? '' : '?' + new URLSearchParams(params).toString().replace(/\+/g, '%20')
}

export function versionSegment(version: unknown): string {
  return typeof version === 'string' && version !== '' ? encodeURIComponent(version) : '_'
}

const PLAIN: Record<string, string> = { '%24': '$', '%26': '&', '%2B': '+', '%2C': ',', '%3B': ';', '%3D': '=', '%3A': ':', '%40': '@' }

/**
 * The server's canonical spelling of a path: decoded once, then every segment percent-encoded with only
 * letters, digits, `-._~` and `!$&'()+,;=:@` left plain ("/blog/a b" => "/blog/a%20b").
 */
export function encodePath(pathname: string): string {
  const decoded = pathname.replace(/(%[0-9a-f]{2})+/gi, (escapes) => {
    try {
      return decodeURIComponent(escapes)
    } catch {
      return escapes
    }
  })

  return decoded
    .split('/')
    .map((segment) =>
      encodeURIComponent(segment)
        .replace(/\*/g, '%2A')
        .replace(/%(?:24|26|2B|2C|3B|3D|3A|40)/g, (escape) => PLAIN[escape] ?? escape),
    )
    .join('/')
}

/**
 * /blog/post => {prefix}/{version}/blog/post.json   /  => {prefix}/{version}/index.json
 */
export function jsonPath(pathname: string, prefix: string, version: unknown, index = 'index'): string {
  const path = encodePath(pathname).replace(/^\/+|\/+$/g, '') || index

  return `${prefix}/${versionSegment(version)}/${path}.json`
}

export function normalizePath(pathname: string, policy: TrailingSlash): string {
  if (pathname === '/' || policy === 'ignore') {
    return pathname
  }

  return policy === 'always' ? pathname.replace(/\/?$/, '/') : pathname.replace(/\/+$/, '')
}
