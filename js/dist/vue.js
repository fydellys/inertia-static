import {
  createStaticHttpClient
} from "./shared-23IYIAAY.js";
import {
  StaticIsland,
  useStaticIsland
} from "./shared-5RLIIBHG.js";
import "./shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "./shared-OVSV4ENE.js";

// js/src/vue/index.ts
function inertiaStatic(options = {}) {
  return createStaticHttpClient(options);
}
function withInertiaStatic(options = {}) {
  return { http: inertiaStatic(options) };
}
function ssrRender(renderToString) {
  return renderToString;
}
export {
  StaticIsland,
  ensureCsrfCookie,
  inertiaStatic,
  ssrRender,
  useStaticIsland,
  withInertiaStatic
};
//# sourceMappingURL=vue.js.map