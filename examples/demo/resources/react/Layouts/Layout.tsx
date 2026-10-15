import { Link, router, usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'
import { useStaticIsland } from '@inertia-version'
import NetworkPanel from '../Components/NetworkPanel'

export default function Layout({ children }: { children: ReactNode }) {
  const page = usePage()
  const props = page.props as Record<string, unknown>
  const island = useStaticIsland<{ user: { id: number; name: string } | null }>('/api/me')
  const { appName, auth, flash, csrf, errors } = props

  return (
    <div className="mx-auto max-w-3xl p-6 font-sans">
      <header className="mb-6 flex items-center justify-between border-b pb-4">
        <strong data-testid="app-name">{(appName as string) ?? '— no appName —'}</strong>
        <nav className="flex gap-3 text-sm">
          <Link href="/" data-testid="nav-home">Home (static)</Link>
          <Link href="/about" data-testid="nav-about">About (static)</Link>
          <Link href="/blog" data-testid="nav-blog">Blog (isr)</Link>
          <Link href="/dashboard" data-testid="nav-dashboard">Dashboard (dynamic)</Link>
          <Link href="/login" data-testid="nav-login">Login</Link>
        </nav>
      </header>

      <p className="mb-4 rounded bg-indigo-50 p-2 text-sm" data-testid="island">
        Island /api/me:{' '}
        {island.loading ? (
          <span>loading…</span>
        ) : island.data?.user ? (
          <span>
            Hello, {island.data.user.name}
            <button className="ml-2 underline" data-testid="logout" onClick={() => router.post('/logout')}>
              Sign out
            </button>
          </span>
        ) : (
          <span>guest</span>
        )}
      </p>

      <main>{children}</main>

      <NetworkPanel />

      <section className="mt-4 rounded border bg-white p-3 text-xs">
        <div><b>component:</b> <span data-testid="component">{page.component}</span></div>
        <div><b>url:</b> <span data-testid="url">{page.url}</span></div>
        <div><b>received props:</b> <span data-testid="prop-keys">{Object.keys(props).sort().join(', ')}</span></div>
        <pre data-testid="shared-props">{JSON.stringify({ appName, auth, flash, csrf, errors }, null, 1)}</pre>
      </section>
    </div>
  )
}

/** Persistent layout: mounted once, kept across visits (the /api/me island loads once). */
export const layout = (page: ReactNode) => <Layout>{page}</Layout>
