import { Head, Link } from '@inertiajs/react'
import { layout } from '../../Layouts/Layout'

interface Props {
  renderedAt: string
  posts: { data: { slug: string; title: string }[]; current_page: number; last_page: number }
}

export default function BlogIndex({ renderedAt, posts }: Props) {
  return (
    <>
      <Head title="Blog" />
      <h1 className="text-2xl font-bold">Blog (ISR) — page {posts.current_page}</h1>
      <p>Rendered at the origin at <span data-testid="rendered-at">{renderedAt}</span></p>
      <ul className="my-3 list-disc pl-6">
        {posts.data.map((post) => (
          <li key={post.slug}>
            <Link href={`/blog/${post.slug}`} data-testid={`post-${post.slug}`}>{post.title}</Link>
          </li>
        ))}
      </ul>
      <div className="flex gap-3">
        {posts.current_page > 1 && <Link href={`/blog?page=${posts.current_page - 1}`} data-testid="prev">← previous</Link>}
        {posts.current_page < posts.last_page && <Link href={`/blog?page=${posts.current_page + 1}`} data-testid="next">next →</Link>}
      </div>
    </>
  )
}

BlogIndex.layout = layout
