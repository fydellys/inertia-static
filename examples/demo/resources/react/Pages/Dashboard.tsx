import { Head, useForm, usePage } from '@inertiajs/react'
import NewsletterForms from '../Components/NewsletterForms'
import { layout } from '../Layouts/Layout'

interface Props {
  renderedAt: string
  secret: string
  firstPost: { id: number; title: string } | null
}

export default function Dashboard({ renderedAt, secret, firstPost }: Props) {
  const flash = (usePage().props as { flash?: string }).flash
  const form = useForm({ title: firstPost?.title ?? '' })

  return (
    <>
      <Head title="Dashboard" />
      <h1 className="text-2xl font-bold">Dashboard (dynamic)</h1>
      {flash && <p className="text-green-700" data-testid="flash">{flash}</p>}
      <p data-testid="secret">{secret}</p>
      <p>Rendered at the origin at <span data-testid="rendered-at">{renderedAt}</span></p>
      {firstPost && (
        <form
          className="mt-4 flex gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            form.post(`/admin/posts/${firstPost.id}`)
          }}
        >
          <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className="flex-1 border p-1" data-testid="post-title-input" />
          <button className="rounded bg-slate-800 px-3 text-white" data-testid="rename">Rename post {firstPost.id}</button>
        </form>
      )}
      <NewsletterForms />
    </>
  )
}

Dashboard.layout = layout
