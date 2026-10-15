// Inertia 3: the static client decorates Inertia's HTTP client.
import { HttpResponseError, xhrHttpClient, type HttpClient } from '@inertiajs/core'
import { useHttp } from '@inertiajs/vue3'
import { inertiaStatic, type StaticManifest } from '@fydellys/inertia-static/vue'
import { recordResponse } from '../devtools/recorder'

export { useStaticIsland } from '@fydellys/inertia-static/vue'

/** Inertia 3 awaits its SSR render: nothing to adapt. */
export const ssrRender = <T>(renderToString: T): T => renderToString

export function staticAppOptions(manifest: StaticManifest) {
  return { http: inertiaStatic({ manifest, client: recordingClient() }) }
}

export const useJsonForm = useHttp

function recordingClient(): HttpClient {
  return {
    async request(config) {
      const started = performance.now()

      try {
        const response = await xhrHttpClient.request(config)
        recordResponse(config.method, config.url, response, started)
        return response
      } catch (error) {
        if (error instanceof HttpResponseError) {
          recordResponse(config.method, config.url, error.response, started)
        }
        throw error
      }
    },
  }
}
