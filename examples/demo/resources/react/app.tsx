import { createInertiaApp } from '@inertiajs/react'
import { hydrateRoot } from 'react-dom/client'
import manifest from '../js/inertia-static.json'
// resources/react/inertia/v2.ts or v3.ts, depending on the installed Inertia (vite.config.js).
import { staticAppOptions } from '@inertia-version'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true })
    return pages[`./Pages/${name}.tsx`] as never
  },
  setup({ el, App, props }) {
    hydrateRoot(el!, <App {...props} />)
  },
  ...staticAppOptions(manifest),
})
