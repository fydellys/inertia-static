// Demo only, Inertia 3 + React: the network panel records what Inertia's HTTP client receives.
import { HttpResponseError, xhrHttpClient, type HttpClient } from '@inertiajs/core'
import { useHttp } from '@inertiajs/react'
import { recordResponse } from '../devtools/recorder'

/** Inertia 3 only features (Pages/Features.tsx). */
export { default as V3Features } from '../Components/V3Features'

/** Inertia's XHR client with the network panel's recorder. */
export function demoClient(): HttpClient {
  return recordingClient()
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
