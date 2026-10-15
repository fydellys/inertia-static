import { useState } from 'react'
import { Head, Link, usePoll } from '@inertiajs/react'
import { layout } from '../Layouts/Layout'

export default function Home({ renderedAt, preview }: { renderedAt: string; preview?: boolean }) {
  // Polling a static page is a partial reload: it must go to the origin, uncached.
  const { start, stop } = usePoll(1500, { only: ['renderedAt'] }, { autoStart: false })
  const [polling, setPolling] = useState(false)

  return (
    <>
      <Head title="Home" />
      {preview && (
        <p className="mb-2 rounded bg-amber-100 p-2 text-sm" data-testid="preview-banner">
          Preview mode: fresh content, not cached. <a href="/preview/disable" className="underline" data-testid="preview-exit">Exit</a>
        </p>
      )}
      <h1 className="text-2xl font-bold">Static home</h1>
      <p>Rendered at the origin at <span data-testid="rendered-at">{renderedAt}</span></p>
      <div className="mt-3 flex gap-3 text-sm">
        <Link href="/blog/post-11" prefetch data-testid="prefetch-link" className="underline">Post 11 (prefetch on hover)</Link>
        {polling ? (
          <button className="rounded border px-2" data-testid="poll-stop" onClick={() => (stop(), setPolling(false))}>Stop polling</button>
        ) : (
          <button className="rounded border px-2" data-testid="poll-start" onClick={() => (start(), setPolling(true))}>Start polling</button>
        )}
      </div>
    </>
  )
}

Home.layout = layout
