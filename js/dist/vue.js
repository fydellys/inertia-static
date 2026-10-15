import {
  createStaticHttpClient
} from "./shared-4JYTRPWR.js";
import {
  StaticIsland,
  useStaticIsland
} from "./shared-WBXXF2V2.js";
import "./shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "./shared-JKP2DSHM.js";

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