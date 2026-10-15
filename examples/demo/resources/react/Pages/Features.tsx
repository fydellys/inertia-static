import { Deferred, Form, Head, InfiniteScroll, Link, router, useRemember, WhenVisible } from '@inertiajs/react'
import { StaticIsland } from '@fydellys/inertia-static/react'
import { V3Features } from '@inertia-version'
import { layout } from '../Layouts/Layout'

interface Props {
  title: string
  renderedAt: string
  deferred?: string
  visible?: string
  posts: { data: { id: number; title: string }[] }
  log: string[]
  plans: string
  likes: number
}

// Inertia features on a static (ISR) page. Each section has its own end-to-end check.
export default function Features({ title, renderedAt, deferred, visible, posts, log, plans, likes }: Props) {
  const [remembered, setRemembered] = useRemember({ note: '' }, 'features-note')

  return (
    <>
      <Head title={title} />
      <h1 className="text-2xl font-bold" data-testid="features-title">{title}</h1>
      <p>Rendered at the origin at <span data-testid="rendered-at">{renderedAt}</span></p>
      <p className="text-sm">
        <Link href="/features" data-testid="features-one">Features</Link> ·{' '}
        <Link href="/features/two" data-testid="features-two">Features two</Link>
      </p>

      <section className="mt-4 space-y-3 text-sm">
        <div>
          <b>Deferred:</b>{' '}
          <Deferred data="deferred" fallback={<span data-testid="deferred">loading…</span>}>
            <span data-testid="deferred">{deferred}</span>
          </Deferred>
        </div>

        <div><b>Once:</b> <span data-testid="plans">{plans}</span></div>

        <div>
          <b>Merge:</b> <span data-testid="log-count">{log.length}</span> entries
          <button className="ml-2 rounded border px-1" data-testid="log-add" onClick={() => router.reload({ only: ['log'] })}>add</button>
          <button className="ml-1 rounded border px-1" data-testid="log-reset" onClick={() => router.reload({ only: ['log'], reset: ['log'] })}>reset</button>
        </div>

        <div>
          <b>Remember:</b>{' '}
          <input
            value={remembered.note}
            onChange={(event) => setRemembered({ note: event.target.value })}
            className="border p-1"
            data-testid="remember-input"
            placeholder="type, leave, come back"
          />
        </div>

        <Form action="/newsletter" method="post" options={{ preserveScroll: true }}>
          {({ errors, processing, wasSuccessful }) => (
            <>
              <b>&lt;Form&gt;:</b> <input name="email" className="border p-1" data-testid="form-email" placeholder="email" />
              <button type="submit" className="ml-1 rounded border px-1" disabled={processing} data-testid="form-submit">Subscribe</button>
              <span className="ml-2 text-red-600" data-testid="form-error">{errors.email ?? ''}</span>
              <span className="ml-2 text-green-700" data-testid="form-ok">{wasSuccessful ? 'subscribed' : ''}</span>
            </>
          )}
        </Form>

        {V3Features && <V3Features likes={likes} />}

        <div>
          <b>Infinite scroll:</b> <span data-testid="posts-count">{posts.data.length}</span> posts
          <InfiniteScroll
            data="posts"
            manual
            next={({ loading, fetch, hasMore }) =>
              hasMore && (
                <button className="rounded border px-1" disabled={loading} data-testid="scroll-more" onClick={fetch}>load more</button>
              )
            }
          >
            <ul className="ml-4 list-disc">
              {posts.data.map((post) => (
                <li key={post.id} data-testid="scroll-item">{post.title}</li>
              ))}
            </ul>
          </InfiniteScroll>
        </div>

        <div style={{ height: 1600 }} className="border-l-2 border-dashed pl-2 text-xs text-slate-400">scroll down for WhenVisible</div>

        <div>
          <b>Visible island (user data):</b>{' '}
          <StaticIsland<{ name: string; orders: number }>
            url="/api/me/orders"
            when="visible"
            as="span"
            fallback={<span data-testid="visible-island">waiting…</span>}
          >
            {({ data }) => <span data-testid="visible-island">{data?.name}, {data?.orders} orders</span>}
          </StaticIsland>
        </div>

        <div>
          <b>WhenVisible:</b>{' '}
          <WhenVisible data="visible" fallback={<span data-testid="visible">waiting…</span>}>
            <span data-testid="visible">{visible}</span>
          </WhenVisible>
        </div>
      </section>
    </>
  )
}

Features.layout = layout
