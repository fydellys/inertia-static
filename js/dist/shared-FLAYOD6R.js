import {
  fetchIsland,
  onceVisible,
  refreshAfterMutations
} from "./shared-6NJEPP6G.js";

// js/src/react/useStaticIsland.ts
import { useCallback, useEffect, useRef, useState } from "react";
function useStaticIsland(url, options = {}) {
  const [data, setData] = useState(options.initial ?? null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [element, setElement] = useState(null);
  const current = useRef(data);
  const loaded = useRef(false);
  const generation = useRef(0);
  const init = useRef(options.init);
  init.current = options.init;
  const initial = useRef(options.initial ?? null);
  initial.current = options.initial ?? null;
  const load = useCallback(async () => {
    const run = ++generation.current;
    loaded.current = true;
    setLoading(true);
    setError(null);
    try {
      const result = await fetchIsland(url, init.current);
      if (run === generation.current) {
        current.current = result;
        setData(result);
      }
    } catch (e) {
      if (run === generation.current) {
        current.current = initial.current;
        setData(initial.current);
        setError(e);
      }
    } finally {
      if (run === generation.current) {
        setLoading(false);
      }
    }
    return current.current;
  }, [url]);
  const visible = options.when === "visible";
  const buffer = options.buffer ?? 0;
  useEffect(() => {
    if (options.immediate !== false && !visible) {
      void load();
    }
  }, [load, options.immediate, visible]);
  useEffect(() => {
    if (options.immediate === false || !visible || !element || loaded.current) {
      return void 0;
    }
    return onceVisible(element, buffer, load);
  }, [load, options.immediate, visible, element, buffer]);
  useEffect(
    () => options.refreshAfterMutations === false ? void 0 : refreshAfterMutations(() => loaded.current ? load() : void 0),
    [load, options.refreshAfterMutations]
  );
  return { data, loading, error, load, target: setElement };
}

// js/src/react/StaticIsland.ts
import { createElement } from "react";
function StaticIsland({ url, when = "mount", buffer = 0, as = "div", init, refreshAfterMutations: refreshAfterMutations2 = true, fallback, children }) {
  const { target, ...island } = useStaticIsland(url, { when, buffer, init, refreshAfterMutations: refreshAfterMutations2 });
  const render = (node) => typeof node === "function" ? node(island) : node;
  return createElement(as, { ref: target }, island.data === null && fallback !== void 0 ? render(fallback) : render(children));
}

export {
  useStaticIsland,
  StaticIsland
};
//# sourceMappingURL=shared-FLAYOD6R.js.map