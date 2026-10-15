import { createInertiaApp } from '@inertiajs/react'
import { hydrateRoot } from 'react-dom/client'
import manifest from '../js/inertia-static.json'
// resources/react/inertia/v2.ts or v3.ts, depending on the installed Inertia (vite.config.js).
import { withInertiaStatic } from '@fydellys/inertia-static/react'
// Demo only: the network panel's recorder (resources/react/inertia/v2.ts or v3.ts).
import { demoClient } from '@inertia-version'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true })
    return pages[`./Pages/${name}.tsx`] as never
  },
  setup({ el, App, props }) {
    hydrateRoot(el!, <App {...props} />)
  },
  // Inertia 2 and 3: the Vite plugin resolves the import to the adapter of the installed Inertia.
  ...withInertiaStatic({ manifest, client: demoClient() }),
})
