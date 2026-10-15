import { Head } from '@inertiajs/react'
import NewsletterForms from '../Components/NewsletterForms'
import { layout } from '../Layouts/Layout'

export default function About({ renderedAt }: { renderedAt: string }) {
  return (
    <>
      <Head title="About" />
      <h1 className="text-2xl font-bold">About (static)</h1>
      <p>Rendered at the origin at <span data-testid="rendered-at">{renderedAt}</span></p>
      <NewsletterForms />
    </>
  )
}

About.layout = layout
