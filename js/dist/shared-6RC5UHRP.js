// js/src/island.ts
async function fetchIsland(url, init) {
  const response = await fetch(url, {
    credentials: "same-origin",
    ...init,
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...init?.headers }
  });
  if (!response.ok) {
    throw new Error(`[inertia-static] Island ${url} failed with status ${response.status}.`);
  }
  return await response.json();
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
  fetchIsland,
  refreshAfterMutations
};
//# sourceMappingURL=shared-6RC5UHRP.js.map