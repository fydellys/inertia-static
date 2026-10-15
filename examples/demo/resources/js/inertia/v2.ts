// Demo only, Inertia 2: the network panel records what axios receives.
import axios from 'axios'
import { reactive } from 'vue'
import { recordResponse } from '../devtools/recorder'

/** Instant visits and optimistic updates are Inertia 3 only. */
export const V3Features = null

/** Installs the recorder on axios. Called before withInertiaStatic(): it sits below the static adapter and sees the CDN's response. */
export function demoClient(): undefined {
  const network = axios.getAdapter(axios.defaults.adapter)
  axios.defaults.adapter = async (config) => {
    const started = performance.now()
    const url = new URL(config.url ?? '', location.href).href

    try {
      const response = await network(config)
      recordResponse(config.method ?? 'get', url, response, started)
      return response
    } catch (error) {
      const response = (error as { response?: { status: number; headers: object } }).response
      if (response) recordResponse(config.method ?? 'get', url, response, started)
      throw error
    }
  }

  return undefined
}

/** Same shape as Inertia 3's useHttp(), for the demo form (Inertia 2 has no useHttp). */
export function useJsonForm<T extends Record<string, unknown>>(initial: T) {
  const form = reactive({
    ...initial,
    errors: {} as Record<string, string>,
    async post(url: string, options: { onSuccess?: (data: never) => void; onError?: () => void } = {}) {
      form.errors = {}
      try {
        const { errors: _errors, post: _post, ...data } = form
        const response = await axios.post(url, data, { headers: { Accept: 'application/json' } })
        options.onSuccess?.(response.data as never)
      } catch (error) {
        const response = (error as { response?: { status: number; data: { errors?: Record<string, string[]> } } }).response
        if (response?.status !== 422) throw error
        form.errors = Object.fromEntries(Object.entries(response.data.errors ?? {}).map(([key, value]) => [key, value[0] ?? '']))
        options.onError?.()
      }
    },
  })

  return form
}
