import {
  useStaticIsland
} from "./shared-G4Y2JLCZ.js";
import {
  MISSING_STATUSES,
  StaticRouting,
  ensureCsrfCookie,
  getHeader
} from "./shared-4NC3HMDQ.js";

// js/src/inertia2.ts
var INSTALLED = /* @__PURE__ */ Symbol.for("fydellys.inertia-static");
function installInertiaStatic(axios, options = {}) {
  const previous = axios.defaults.adapter;
  if (typeof previous === "function" && INSTALLED in previous) {
    return () => {
    };
  }
  if (typeof previous !== "function" && !axios.getAdapter) {
    throw new Error("[inertia-static] Unsupported axios version: axios.getAdapter() is missing (axios >= 1.6 is required).");
  }
  const base = typeof previous === "function" ? previous : axios.getAdapter(previous);
  const routing = new StaticRouting(options);
  const adapter = async (config) => {
    const request = {
      method: config.method ?? "get",
      url: new URL(config.url ?? "", config.baseURL ?? window.location.href).href,
      headers: config.headers,
      data: config.params
    };
    const decision = routing.decide(request, await routing.loadManifest());
    routing.log(request.url, decision);
    if (!decision.rewrite || !decision.url) {
      const response = await base(config);
      return routing.needsInertiaHeader(request, response) ? withInertiaHeader(response) : response;
    }
    try {
      const response = await base({ ...config, url: decision.url, baseURL: void 0, params: void 0 });
      return routing.isPageJson(response) ? withInertiaHeader(response) : missing(config, request);
    } catch (error) {
      const status = error.response?.status;
      if (status !== void 0 && MISSING_STATUSES.includes(status)) {
        return missing(config, request);
      }
      throw error;
    }
  };
  const missing = (config, request) => {
    if (routing.options.onMissing === "origin") {
      return base(config);
    }
    return Promise.resolve({
      status: 409,
      statusText: "Conflict",
      data: "",
      headers: { "x-inertia-location": routing.reloadLocation(request) },
      config,
      request: {}
    });
  };
  Object.defineProperty(adapter, INSTALLED, { value: true });
  axios.defaults.adapter = adapter;
  return () => {
    axios.defaults.adapter = previous;
  };
}
function withInertiaHeader(response) {
  if (getHeader(response.headers, "x-inertia") === void 0) {
    ;
    response.headers["x-inertia"] = "true";
  }
  return response;
}
export {
  ensureCsrfCookie,
  installInertiaStatic,
  useStaticIsland
};
//# sourceMappingURL=inertia2.js.map