import {
  createStaticHttpClient
} from "./shared-4JYTRPWR.js";
import {
  StaticIsland,
  useStaticIsland
} from "./shared-FLAYOD6R.js";
import "./shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "./shared-JKP2DSHM.js";

// js/src/react/index.ts
function inertiaStatic(options = {}) {
  return createStaticHttpClient(options);
}
function withInertiaStatic(options = {}) {
  return { http: inertiaStatic(options) };
}
export {
  StaticIsland,
  ensureCsrfCookie,
  inertiaStatic,
  useStaticIsland,
  withInertiaStatic
};
//# sourceMappingURL=react.js.map