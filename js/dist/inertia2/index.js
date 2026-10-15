import {
  StaticIsland,
  useStaticIsland
} from "../shared-5RLIIBHG.js";
import {
  installInertiaStatic,
  ssrRender
} from "../shared-YYCU7CCX.js";
import "../shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "../shared-OVSV4ENE.js";

// js/src/inertia2/index.ts
import axios from "axios";
function withInertiaStatic(options = {}) {
  installInertiaStatic(axios, options);
  return {};
}
export {
  StaticIsland,
  ensureCsrfCookie,
  installInertiaStatic,
  ssrRender,
  useStaticIsland,
  withInertiaStatic
};
//# sourceMappingURL=index.js.map