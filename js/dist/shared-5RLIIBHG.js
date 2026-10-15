import {
  fetchIsland,
  onceVisible,
  refreshAfterMutations
} from "./shared-6NJEPP6G.js";

// js/src/vue/useStaticIsland.ts
import { getCurrentScope, getCurrentInstance, onMounted, onScopeDispose, ref, shallowRef, watch } from "vue";
function useStaticIsland(url, options = {}) {
  const data = shallowRef(options.initial ?? null);
  const loading = ref(false);
  const error = shallowRef(null);
  const target = ref(null);
  let loaded = false;
  const load = async () => {
    if (typeof window === "undefined") {
      return data.value;
    }
    loaded = true;
    loading.value = true;
    error.value = null;
    try {
      data.value = await fetchIsland(url, options.init);
    } catch (e) {
      error.value = e;
    } finally {
      loading.value = false;
    }
    return data.value;
  };
  const visible = options.when === "visible";
  if (options.immediate !== false && !visible) {
    if (getCurrentInstance()) {
      onMounted(load);
    } else {
      void load();
    }
  }
  if (options.immediate !== false && visible && typeof window !== "undefined") {
    let stopObserving = () => {
    };
    const stopWatching = watch(
      target,
      (element) => {
        stopObserving();
        stopObserving = element && !loaded ? onceVisible(element, options.buffer ?? 0, load) : () => {
        };
      },
      { immediate: true, flush: "post" }
    );
    if (getCurrentScope()) {
      onScopeDispose(() => (stopWatching(), stopObserving()));
    }
  }
  if (options.refreshAfterMutations !== false && typeof document !== "undefined") {
    const stop = refreshAfterMutations(() => loaded ? load() : void 0);
    if (getCurrentScope()) {
      onScopeDispose(stop);
    }
  }
  return { data, loading, error, load, target };
}

// js/src/vue/StaticIsland.ts
import { defineComponent, h } from "vue";
var StaticIsland = defineComponent({
  name: "StaticIsland",
  props: {
    url: { type: String, required: true },
    when: { type: String, default: "mount" },
    buffer: { type: Number, default: 0 },
    as: { type: String, default: "div" },
    init: { type: Object, default: void 0 },
    refreshAfterMutations: { type: Boolean, default: true }
  },
  slots: Object,
  setup(props, { slots }) {
    const island = useStaticIsland(props.url, {
      when: props.when,
      buffer: props.buffer,
      init: props.init,
      refreshAfterMutations: props.refreshAfterMutations
    });
    return () => {
      const scope = {
        data: island.data.value,
        loading: island.loading.value,
        error: island.error.value,
        load: island.load
      };
      const content = island.data.value === null && slots.fallback ? slots.fallback(scope) : slots.default?.(scope);
      return h(props.as, { ref: island.target }, content);
    };
  }
});

export {
  useStaticIsland,
  StaticIsland
};
//# sourceMappingURL=shared-5RLIIBHG.js.map