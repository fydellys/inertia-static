import {
  createStaticHttpClient
} from "./shared-23IYIAAY.js";
import {
  StaticIsland,
  useStaticIsland
} from "./shared-CAKRSQPB.js";
import "./shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "./shared-OVSV4ENE.js";

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