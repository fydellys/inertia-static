import { Head, Link } from '@inertiajs/react'
import { layout } from '../../Layouts/Layout'

interface Props {
  renderedAt: string
  canonical: string
  post: { id: number; slug: string; title: string; body: string }
}

export default function BlogShow({ renderedAt, canonical, post }: Props) {
  return (
    <>
      <Head title={post.title}>
        <meta name="description" content={post.body} />
        <meta property="og:title" content={post.title} />
        <meta property="og:type" content="article" />
        <link rel="canonical" href={canonical} />
      </Head>
      <h1 className="text-2xl font-bold" data-testid="post-title">{post.title}</h1>
      <p>{post.body}</p>
      <p>Rendered at the origin at <span data-testid="rendered-at">{renderedAt}</span></p>
      <Link href="/blog" data-testid="back">← back to the blog</Link>
    </>
  )
}

BlogShow.layout = layout
