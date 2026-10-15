import {
  fetchIsland,
  refreshAfterMutations
} from "./shared-6RC5UHRP.js";

// js/src/vue/useStaticIsland.ts
import { getCurrentScope, getCurrentInstance, onMounted, onScopeDispose, ref, shallowRef } from "vue";
function useStaticIsland(url, options = {}) {
  const data = shallowRef(options.initial ?? null);
  const loading = ref(false);
  const error = shallowRef(null);
  const load = async () => {
    if (typeof window === "undefined") {
      return data.value;
    }
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
  if (options.immediate !== false) {
    if (getCurrentInstance()) {
      onMounted(load);
    } else {
      void load();
    }
  }
  if (options.refreshAfterMutations !== false && typeof document !== "undefined") {
    const stop = refreshAfterMutations(load);
    if (getCurrentScope()) {
      onScopeDispose(stop);
    }
  }
  return { data, loading, error, load };
}

export {
  useStaticIsland
};
//# sourceMappingURL=shared-N4T7YWF5.js.map