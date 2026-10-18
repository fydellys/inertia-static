import {
  createStaticHttpClient
} from "./shared-HENI7VBU.js";
import {
  StaticIsland,
  useStaticIsland
} from "./shared-FLAYOD6R.js";
import "./shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "./shared-U2YX7IMO.js";

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