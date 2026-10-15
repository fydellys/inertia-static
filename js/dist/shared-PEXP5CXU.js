import {
  MISSING_STATUSES,
  StaticRouting,
  getHeader
} from "./shared-JKP2DSHM.js";

// js/src/inertia2/adapter.ts
var INSTALLED = /* @__PURE__ */ Symbol.for("fydellys.inertia-static");
function ssrRender(renderToString) {
  const nested = [];
  const nonce = globalThis.crypto?.randomUUID?.() ?? Math.random().toString(36).slice(2) + Date.now().toString(36);
  let rendering = false;
  return ((app) => {
    if (rendering) {
      nested.push(renderToString(app));
      return `<!--inertia-static-ssr:${nonce}:${nested.length - 1}-->`;
    }
    rendering = true;
    return renderToString(app).then(async (html) => {
      const parts = await Promise.all(nested);
      return parts.reduce((out, part, index) => out.replace(`<!--inertia-static-ssr:${nonce}:${index}-->`, () => part), html);
    });
  });
}
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
    await routing.ensureCsrfCookie({
      method: config.method ?? "get",
      url: new URL(config.url ?? "", config.baseURL ?? window.location.href).href
    });
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
  ssrRender,
  installInertiaStatic
};
//# sourceMappingURL=shared-PEXP5CXU.js.map