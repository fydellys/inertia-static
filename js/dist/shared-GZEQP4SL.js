import {
  fetchIsland,
  refreshAfterMutations
} from "./shared-6RC5UHRP.js";

// js/src/react/useStaticIsland.ts
import { useCallback, useEffect, useRef, useState } from "react";
function useStaticIsland(url, options = {}) {
  const [data, setData] = useState(options.initial ?? null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const current = useRef(data);
  const init = useRef(options.init);
  init.current = options.init;
  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      current.current = await fetchIsland(url, init.current);
      setData(current.current);
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
    }
    return current.current;
  }, [url]);
  useEffect(() => {
    if (options.immediate !== false) {
      void load();
    }
  }, [load, options.immediate]);
  useEffect(() => options.refreshAfterMutations === false ? void 0 : refreshAfterMutations(load), [load, options.refreshAfterMutations]);
  return { data, loading, error, load };
}

export {
  useStaticIsland
};
//# sourceMappingURL=shared-GZEQP4SL.js.map