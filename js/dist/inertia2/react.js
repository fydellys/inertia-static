import {
  StaticIsland,
  useStaticIsland
} from "../shared-CAKRSQPB.js";
import {
  installInertiaStatic
} from "../shared-YYCU7CCX.js";
import "../shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "../shared-OVSV4ENE.js";

// js/src/inertia2/react.ts
import axios from "axios";
function withInertiaStatic(options = {}) {
  installInertiaStatic(axios, options);
  return {};
}
export {
  StaticIsland,
  ensureCsrfCookie,
  installInertiaStatic,
  useStaticIsland,
  withInertiaStatic
};
//# sourceMappingURL=react.js.map