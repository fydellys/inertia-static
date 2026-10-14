import { createInertiaApp } from '@inertiajs/vue3'
import { createSSRApp, h, type DefineComponent } from 'vue'
import manifest from './inertia-static.json'
// resources/js/inertia/v2.ts or v3.ts, depending on the installed Inertia (vite.config.js).
import { staticAppOptions } from '@inertia-version'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]!
  },
  setup({ el, App, props, plugin }) {
    createSSRApp({ render: () => h(App, props) }).use(plugin).mount(el!)
  },
  ...staticAppOptions(manifest),
})
