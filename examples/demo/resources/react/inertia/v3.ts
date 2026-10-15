// Inertia 3 + React: the static client decorates Inertia's HTTP client.
import { HttpResponseError, xhrHttpClient, type HttpClient } from '@inertiajs/core'
import { useHttp } from '@inertiajs/react'
import { inertiaStatic, type StaticManifest } from '@fydellys/inertia-static/react'
import { recordResponse } from '../devtools/recorder'

export { useStaticIsland } from '@fydellys/inertia-static/react'

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
