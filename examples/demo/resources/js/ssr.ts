import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from 'vue/server-renderer'
import { createSSRApp, h, type DefineComponent } from 'vue'
import { ssrRender } from '@fydellys/inertia-static/vue'

createServer((page) =>
  createInertiaApp({
    page,
    render: ssrRender(renderToString),
    resolve: (name) => {
      const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true })
      return pages[`./Pages/${name}.vue`]!
    },
    setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
  }),
)
