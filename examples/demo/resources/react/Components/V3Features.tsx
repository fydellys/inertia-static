import { Link, router } from '@inertiajs/react'

// Inertia 3 only: instant visits and optimistic updates.
export default function V3Features({ likes }: { likes: number }) {
  const like = () => router.optimistic((props) => ({ likes: (props.likes as number) + 1 })).post('/features/like', {}, { preserveScroll: true })

  return (
    <div>
      <b>Inertia 3:</b>{' '}
      <Link href="/about" component="About" data-testid="instant-about">instant visit to About</Link>
      {' '}· likes <span data-testid="likes">{likes}</span>
      <button className="ml-1 rounded border px-1" data-testid="like" onClick={like}>like (optimistic)</button>
    </div>
  )
}
