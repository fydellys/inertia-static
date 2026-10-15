import {
  StaticIsland,
  useStaticIsland
} from "../shared-WBXXF2V2.js";
import {
  installInertiaStatic,
  ssrRender
} from "../shared-PEXP5CXU.js";
import "../shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "../shared-JKP2DSHM.js";

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