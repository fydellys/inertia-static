// js/src/manifest.ts
var DEFAULT_PREFIX = "/__inertia";
var DEFAULT_PREVIEW_COOKIE = "inertia_static_preview";
var emptyManifest = () => ({ prefix: DEFAULT_PREFIX, previewCookie: DEFAULT_PREVIEW_COOKIE, csrfCookieUrl: null, routes: [] });
function compileManifest(manifest) {
  return {
    prefix: "/" + (manifest.prefix || DEFAULT_PREFIX).replace(/^\/+|\/+$/g, ""),
    previewCookie: manifest.previewCookie || DEFAULT_PREVIEW_COOKIE,
    csrfCookieUrl: manifest.csrfCookieUrl || null,
    routes: (manifest.routes ?? []).flatMap((route) => {
      try {
        return [{ ...route, query: route.query ?? [], regex: new RegExp(route.pattern) }];
      } catch {
        return [];
      }
    })
  };
}
function matchRoute(manifest, url) {
  return manifest.routes.find(
    (route) => (!route.domain || route.domain === url.hostname) && route.regex.test(url.pathname)
  );
}

// js/src/urls.ts
function canonicalQuery(url, whitelist) {
  const params = [...url.searchParams.entries()].filter(([, value]) => value !== "");
  if (params.some(([key]) => !whitelist.includes(key.replace(/\[.*$/, "")))) {
    return null;
  }
  params.sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0);
  return params.length === 0 ? "" : "?" + new URLSearchParams(params).toString().replace(/\+/g, "%20");
}
function versionSegment(version) {
  return typeof version === "string" && version !== "" ? encodeURIComponent(version) : "_";
}
function jsonPath(pathname, prefix, version, index = "index") {
  const path = pathname.replace(/^\/+|\/+$/g, "") || index;
  return `${prefix}/${versionSegment(version)}/${path}.json`;
}
function normalizePath(pathname, policy) {
  if (pathname === "/" || policy === "ignore") {
    return pathname;
  }
  return policy === "always" ? pathname.replace(/\/?$/, "/") : pathname.replace(/\/+$/, "");
}

// js/src/headers.ts
function getHeader(headers, name) {
  if (!headers) {
    return void 0;
  }
  const key = Object.keys(headers).find((header) => header.toLowerCase() === name.toLowerCase());
  return key === void 0 ? void 0 : headers[key];
}
function hasHeader(headers, name) {
  const value = getHeader(headers, name);
  return value !== void 0 && value !== null && value !== false && value !== "";
}
var BYPASS_HEADERS = [
  "X-Inertia-Partial-Component",
  "X-Inertia-Partial-Data",
  "X-Inertia-Partial-Except",
  "X-Inertia-Reset",
  "X-Inertia-Infinite-Scroll-Merge-Intent",
  "X-Inertia-Static-Bypass",
  "Precognition"
];

// js/src/core.ts
var MISSING_STATUSES = [403, 404, 410];
var XSRF_COOKIE = "XSRF-TOKEN";
var csrfCookieRequest = null;
var StaticRouting = class {
  manifest;
  loading = null;
  options;
  constructor(options = {}) {
    this.manifest = options.manifest ? compileManifest(options.manifest) : null;
    this.options = {
      manifestUrl: options.manifestUrl ?? `${options.manifest?.prefix ?? DEFAULT_PREFIX}/manifest.json`,
      trailingSlash: options.trailingSlash ?? "never",
      index: options.index ?? "index",
      onMissing: options.onMissing ?? "reload",
      debug: options.debug ?? false
    };
  }
  /**
   * Whether (and where) a visit is rewritten.
   *
   * An Inertia visit to a static page never uses the page's HTML URL: CDNs that
   * ignore "Vary: X-Inertia" would answer it with the cached HTML (inertia#1923).
   * - cacheable visits go to /{prefix}/{version}/{path}.json
   * - visits that need fresh data (router.reload / polling, partial reloads,
   *   deferred props, non canonical query) go to /{prefix}/live/{version}/{path}.json
   */
  decide(request, manifest = this.manifest) {
    if (!manifest || manifest.routes.length === 0) {
      return { rewrite: false, reason: "no-manifest" };
    }
    if (request.method.toLowerCase() !== "get") {
      return { rewrite: false, reason: "method" };
    }
    if (!hasHeader(request.headers, "X-Inertia")) {
      return { rewrite: false, reason: "not-a-visit" };
    }
    if (request.data && typeof request.data === "object" && Object.keys(request.data).length > 0) {
      return { rewrite: false, reason: "data" };
    }
    const url = new URL(request.url, window.location.href);
    if (url.origin !== window.location.origin) {
      return { rewrite: false, reason: "cross-origin" };
    }
    const route = matchRoute(manifest, url);
    if (!route) {
      return { rewrite: false, reason: "not-static" };
    }
    const path = normalizePath(url.pathname, this.options.trailingSlash);
    const version = getHeader(request.headers, "X-Inertia-Version");
    const live = (reason, search) => ({
      rewrite: true,
      cacheable: false,
      reason: `live:${reason}`,
      url: url.origin + jsonPath(path, `${manifest.prefix}/live`, version, this.options.index) + search
    });
    if (hasCookie(manifest.previewCookie)) {
      return live("preview", url.search);
    }
    const bypass = BYPASS_HEADERS.find((header) => hasHeader(request.headers, header));
    if (bypass) {
      return live(bypass.toLowerCase(), url.search);
    }
    if (String(getHeader(request.headers, "Cache-Control") ?? "").includes("no-cache")) {
      return live("reload", url.search);
    }
    const query = canonicalQuery(url, route.query);
    if (query === null) {
      return live("query", url.search);
    }
    return {
      rewrite: true,
      cacheable: true,
      reason: route.name ?? route.pattern,
      url: url.origin + jsonPath(path, manifest.prefix, version, this.options.index) + query
    };
  }
  /**
   * Static pages carry no CSRF cookie. When the manifest asks for it (Laravel 12),
   * fetch the XSRF-TOKEN cookie before a same-origin request that changes data:
   * Inertia (and axios) then send the token from the cookie themselves.
   */
  async ensureCsrfCookie(request) {
    const manifest = await this.loadManifest();
    if (!manifest?.csrfCookieUrl || ["get", "head", "options"].includes(request.method.toLowerCase()) || hasCookie(XSRF_COOKIE) || new URL(request.url, window.location.href).origin !== window.location.origin) {
      return;
    }
    csrfCookieRequest ??= fetch(manifest.csrfCookieUrl, { credentials: "same-origin" }).catch(() => void 0).finally(() => {
      csrfCookieRequest = null;
    });
    await csrfCookieRequest;
  }
  /** A 409 + X-Inertia-Location makes Inertia perform a full page load of the original URL. */
  reloadLocation(request) {
    return new URL(request.url, window.location.href).href;
  }
  /**
   * A visit can end on a page JSON without its "x-inertia" header: the browser
   * followed a redirect inside the XHR (e.g. `return back()` to a static page,
   * rewritten to the live JSON URL), or a proxy stripped the header. When the
   * body IS an Inertia page, the header must be restored (inertia#1923).
   */
  needsInertiaHeader(request, response) {
    return getHeader(response.headers, "x-inertia") === void 0 && hasHeader(request.headers, "X-Inertia") && response.status === 200 && this.isInertiaPage(response);
  }
  isInertiaPage(response) {
    if (!String(getHeader(response.headers, "content-type") ?? "").includes("json") || typeof response.data !== "string") {
      return false;
    }
    try {
      const page = JSON.parse(response.data);
      return typeof page.component === "string" && typeof page.url === "string" && "version" in page && typeof page.props === "object" && page.props !== null;
    } catch {
      return false;
    }
  }
  isPageJson(response) {
    const type = String(getHeader(response.headers, "content-type") ?? "");
    if (type && !type.includes("json")) {
      return false;
    }
    return typeof response.data === "string" ? response.data.trimStart().startsWith("{") : true;
  }
  async loadManifest() {
    if (this.manifest || this.options.manifestUrl === false || typeof fetch === "undefined") {
      return this.manifest;
    }
    this.loading ??= fetch(this.options.manifestUrl, { headers: { Accept: "application/json" } }).then((response) => response.ok ? response.json() : Promise.reject(new Error(String(response.status)))).then((manifest) => compileManifest(manifest)).catch(() => emptyManifest());
    this.manifest = await this.loading;
    return this.manifest;
  }
  log(url, decision) {
    if (this.options.debug) {
      console.debug("[inertia-static]", url, decision);
    }
  }
};
function hasCookie(name) {
  if (typeof document === "undefined") {
    return false;
  }
  return document.cookie.split("; ").some((cookie) => cookie.startsWith(`${name}=`) && cookie.length > name.length + 1);
}

// js/src/csrf.ts
var pending = null;
function ensureCsrfCookie(url = "/sanctum/csrf-cookie", cookieName = "XSRF-TOKEN") {
  if (typeof document !== "undefined" && document.cookie.split("; ").some((c) => c.startsWith(`${cookieName}=`))) {
    return Promise.resolve();
  }
  pending ??= fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } }).then((response) => {
    if (!response.ok) {
      throw new Error(`[inertia-static] CSRF cookie request failed (${response.status}).`);
    }
  }).finally(() => {
    pending = null;
  });
  return pending;
}

export {
  getHeader,
  compileManifest,
  matchRoute,
  canonicalQuery,
  jsonPath,
  MISSING_STATUSES,
  StaticRouting,
  ensureCsrfCookie
};
//# sourceMappingURL=shared-OVSV4ENE.js.map