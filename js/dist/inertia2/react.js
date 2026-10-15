import {
  StaticIsland,
  useStaticIsland
} from "../shared-FLAYOD6R.js";
import {
  installInertiaStatic
} from "../shared-PEXP5CXU.js";
import "../shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "../shared-JKP2DSHM.js";

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