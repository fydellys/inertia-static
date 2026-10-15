import { useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { useJsonForm } from '@inertia-version'

// Two ways of submitting a form, to compare them on static and dynamic pages:
// A. useForm (an Inertia visit): POST /newsletter → return back() (redirect + flash + session errors)
// B. useHttp (plain JSON request): POST /api/newsletter → JSON, 422 validation errors in the response
export default function NewsletterForms() {
  const page = usePage()
  const form = useForm({ email: '' })
  const [resultA, setResultA] = useState('')
  const http = useJsonForm({ email: '' })
  const [resultB, setResultB] = useState('')

  return (
    <section className="mt-6 grid gap-4 rounded border bg-white p-3 text-sm md:grid-cols-2">
      <form
        data-testid="form-a"
        onSubmit={(event) => {
          event.preventDefault()
          setResultA('')
          form.post('/newsletter', {
            preserveScroll: true,
            onSuccess: (next) => setResultA(`ok, now on ${next.url}`),
            onError: () => setResultA('validation error'),
          })
        }}
      >
        <h3 className="font-bold">A. useForm → return back()</h3>
        <input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="w-full border p-1" placeholder="email" data-testid="a-email" />
        <p className="text-red-600" data-testid="a-error">{form.errors.email ?? ''}</p>
        <p className="text-green-700" data-testid="a-flash">{(page as { flash?: { newsletter?: string } }).flash?.newsletter ?? ''}</p>
        <p data-testid="a-result">{resultA}</p>
        <button className="mt-1 rounded bg-slate-800 px-3 py-1 text-white" data-testid="a-submit">Subscribe</button>
      </form>

      <form
        data-testid="form-b"
        onSubmit={async (event) => {
          event.preventDefault()
          setResultB('')
          await http.post('/api/newsletter', {
            onSuccess: (data: { message: string }) => setResultB(data.message),
            onError: () => setResultB('validation error'),
          })
        }}
      >
        <h3 className="font-bold">B. useHttp → JSON</h3>
        <input value={http.data.email} onChange={(e) => http.setData('email', e.target.value)} className="w-full border p-1" placeholder="email" data-testid="b-email" />
        <p className="text-red-600" data-testid="b-error">{(http.errors as Record<string, string>).email ?? ''}</p>
        <p className="text-green-700" data-testid="b-result">{resultB}</p>
        <button className="mt-1 rounded bg-slate-800 px-3 py-1 text-white" data-testid="b-submit">Subscribe</button>
      </form>
    </section>
  )
}
