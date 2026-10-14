import { createInertiaApp } from '@inertiajs/vue3'
import { inertiaStatic } from '@fydellys/inertia-static/vue'
import { createSSRApp, h, type DefineComponent } from 'vue'
import manifest from './inertia-static.json'
import { recordingClient } from './devtools/recorder'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]!
  },
  setup({ el, App, props, plugin }) {
    createSSRApp({ render: () => h(App, props) }).use(plugin).mount(el!)
  },
  http: inertiaStatic({ manifest, client: recordingClient() }),
})
