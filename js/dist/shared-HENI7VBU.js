import {
  MISSING_STATUSES,
  StaticRouting
} from "./shared-U2YX7IMO.js";

// js/src/client.ts
import {
  HttpResponseError,
  xhrHttpClient
} from "@inertiajs/core";
var StaticHttpClient = class {
  routing;
  inner;
  constructor(options = {}) {
    this.inner = options.client ?? xhrHttpClient;
    this.routing = new StaticRouting(options);
  }
  async request(config) {
    await this.routing.ensureCsrfCookie(config);
    const decision = this.routing.decide(config, await this.routing.loadManifest());
    this.routing.log(config.url, decision);
    if (!decision.rewrite || !decision.url) {
      const response = await this.inner.request(config);
      return this.routing.needsInertiaHeader(config, response) ? withInertiaHeader(response) : response;
    }
    try {
      const response = await this.inner.request({ ...config, url: decision.url });
      return this.routing.isPageJson(response) ? withInertiaHeader(response) : this.missing(config);
    } catch (error) {
      if (error instanceof HttpResponseError && MISSING_STATUSES.includes(error.response.status)) {
        return this.missing(config);
      }
      throw error;
    }
  }
  /** Whether (and where) a request is rewritten. Exposed for debugging/tests. */
  decide(config, manifest) {
    return manifest === void 0 ? this.routing.decide(config) : this.routing.decide(config, manifest);
  }
  async missing(config) {
    if (this.routing.options.onMissing === "origin") {
      return this.inner.request(config);
    }
    return { status: 409, data: "", headers: { "x-inertia-location": this.routing.reloadLocation(config) } };
  }
};
function withInertiaHeader(response) {
  return { ...response, headers: { ...response.headers, "x-inertia": "true" } };
}
function createStaticHttpClient(options = {}) {
  return new StaticHttpClient(options);
}

export {
  StaticHttpClient,
  createStaticHttpClient
};
//# sourceMappingURL=shared-HENI7VBU.js.map