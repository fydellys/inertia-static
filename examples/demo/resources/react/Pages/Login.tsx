import { Head, useForm } from '@inertiajs/react'
import { layout } from '../Layouts/Layout'

export default function Login() {
  const form = useForm({ email: 'alice@example.com', password: 'password' })

  return (
    <>
      <Head title="Login" />
      <h1 className="text-2xl font-bold">Login (dynamic)</h1>
      <form
        className="mt-3 flex flex-col gap-2"
        onSubmit={(event) => {
          event.preventDefault()
          form.post('/login')
        }}
      >
        <input value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} className="border p-1" data-testid="email" />
        <input value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} type="password" className="border p-1" data-testid="password" />
        {form.errors.email && <p className="text-red-600">{form.errors.email}</p>}
        <button type="submit" className="rounded bg-slate-800 p-2 text-white" data-testid="submit">Sign in</button>
      </form>
    </>
  )
}

Login.layout = layout
