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
      const response = await fetch(url, {
        credentials: "same-origin",
        ...options.init,
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...options.init?.headers }
      });
      if (!response.ok) {
        throw new Error(`[inertia-static] Island ${url} failed with status ${response.status}.`);
      }
      data.value = await response.json();
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
function refreshAfterMutations(callback) {
  const pending = /* @__PURE__ */ new Set();
  const onStart = (event) => {
    const visit = event.detail?.visit;
    if (visit?.method && visit.method.toLowerCase() !== "get") {
      pending.add(visit.id ?? visit);
    }
  };
  const onFinish = (event) => {
    const visit = event.detail?.visit;
    if (visit && pending.delete(visit.id ?? visit)) {
      void callback();
    }
  };
  document.addEventListener("inertia:start", onStart);
  document.addEventListener("inertia:finish", onFinish);
  return () => {
    document.removeEventListener("inertia:start", onStart);
    document.removeEventListener("inertia:finish", onFinish);
  };
}

export {
  useStaticIsland
};
//# sourceMappingURL=shared-G4Y2JLCZ.js.map