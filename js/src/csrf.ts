let pending: Promise<void> | null = null

/**
 * Static pages carry no session nor CSRF cookie. Call this before submitting
 * a form from a static page: it fetches the XSRF-TOKEN cookie once (Sanctum's
 * /sanctum/csrf-cookie or any dynamic endpoint of yours that starts a session).
 */
export function ensureCsrfCookie(url = '/sanctum/csrf-cookie', cookieName = 'XSRF-TOKEN'): Promise<void> {
  if (typeof document !== 'undefined' && document.cookie.split('; ').some((c) => c.startsWith(`${cookieName}=`))) {
    return Promise.resolve()
  }

  pending ??= fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`[inertia-static] CSRF cookie request failed (${response.status}).`)
      }
    })
    .finally(() => {
      pending = null
    })

  return pending
}
