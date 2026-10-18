import {
  StaticIsland,
  useStaticIsland
} from "../shared-FLAYOD6R.js";
import {
  installInertiaStatic
} from "../shared-2Z2XTITC.js";
import "../shared-6NJEPP6G.js";
import {
  ensureCsrfCookie
} from "../shared-U2YX7IMO.js";

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