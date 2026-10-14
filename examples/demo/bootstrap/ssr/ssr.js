import { computed, createSSRApp, createTextVNode, createVNode, defineComponent, getCurrentInstance, getCurrentScope, h, mergeProps, onMounted, onScopeDispose, onUnmounted, reactive, ref, shallowRef, toDisplayString, unref, useSSRContext, withCtx } from "vue";
import { renderToString, ssrInterpolate, ssrRenderAttr, ssrRenderAttrs, ssrRenderClass, ssrRenderComponent, ssrRenderList, ssrRenderSlot } from "vue/server-renderer";
import { Head, Link, createInertiaApp, useForm, useHttp, usePage, usePoll } from "@inertiajs/vue3";
import "@inertiajs/core";
import createServer from "@inertiajs/vue3/server";
//#region \0rolldown/runtime.js
var __defProp = Object.defineProperty;
var __exportAll = (all, no_symbols) => {
	let target = {};
	for (var name in all) __defProp(target, name, {
		get: all[name],
		enumerable: true
	});
	if (!no_symbols) __defProp(target, Symbol.toStringTag, { value: "Module" });
	return target;
};
//#endregion
//#region ../../js/dist/vue.js
function useStaticIsland(url, options = {}) {
	const data = shallowRef(options.initial ?? null);
	const loading = ref(false);
	const error = shallowRef(null);
	const load = async () => {
		if (typeof window === "undefined") return data.value;
		loading.value = true;
		error.value = null;
		try {
			const response = await fetch(url, {
				credentials: "same-origin",
				...options.init,
				headers: {
					Accept: "application/json",
					"X-Requested-With": "XMLHttpRequest",
					...options.init?.headers
				}
			});
			if (!response.ok) throw new Error(`[inertia-static] Island ${url} failed with status ${response.status}.`);
			data.value = await response.json();
		} catch (e) {
			error.value = e;
		} finally {
			loading.value = false;
		}
		return data.value;
	};
	if (options.immediate !== false) {
		if (getCurrentInstance()) onMounted(load);
		else load();
	}
	if (options.refreshAfterMutations !== false && typeof document !== "undefined") {
		const stop = refreshAfterMutations(load);
		if (getCurrentScope()) onScopeDispose(stop);
	}
	return {
		data,
		loading,
		error,
		load
	};
}
function refreshAfterMutations(callback) {
	const pending = /* @__PURE__ */ new Set();
	const onStart = (event) => {
		const visit = event.detail?.visit;
		if (visit?.method && visit.method.toLowerCase() !== "get") pending.add(visit.id ?? visit);
	};
	const onFinish = (event) => {
		const visit = event.detail?.visit;
		if (visit && pending.delete(visit.id ?? visit)) callback();
	};
	document.addEventListener("inertia:start", onStart);
	document.addEventListener("inertia:finish", onFinish);
	return () => {
		document.removeEventListener("inertia:start", onStart);
		document.removeEventListener("inertia:finish", onFinish);
	};
}
//#endregion
//#region resources/js/devtools/recorder.ts
var network = reactive({
	requests: [],
	errors: []
});
if (typeof document !== "undefined") for (const name of ["httpException", "networkError"]) document.addEventListener(`inertia:${name}`, (event) => {
	const detail = event.detail;
	network.errors.unshift(`${name}: ${detail?.response?.status ?? detail?.error?.message ?? "?"}`);
});
//#endregion
//#region resources/js/Components/NetworkPanel.vue?vue&type=script&setup=true&lang.ts
var NetworkPanel_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	__name: "NetworkPanel",
	__ssrInlineRender: true,
	setup(__props) {
		const mounted = ref(false);
		const initial = ref({
			status: "?",
			cdn: "?",
			origin: "?"
		});
		const origin = ref(null);
		async function refreshOrigin() {
			const response = await fetch("/__origin/stats", { cache: "no-store" });
			origin.value = await response.json();
		}
		const onFinish = () => setTimeout(refreshOrigin, 150);
		onMounted(() => {
			mounted.value = true;
			const nav = performance.getEntriesByType("navigation")[0];
			const timing = (name) => nav?.serverTiming?.find((entry) => entry.name === name)?.description ?? "-";
			const cdn = timing("cdn") !== "-" ? timing("cdn") : timing("cfCacheStatus");
			initial.value = {
				status: nav?.responseStatus ?? "?",
				cdn,
				origin: timing("origin")
			};
			refreshOrigin();
			document.addEventListener("inertia:finish", onFinish);
		});
		onUnmounted(() => document.removeEventListener("inertia:finish", onFinish));
		return (_ctx, _push, _parent, _attrs) => {
			if (mounted.value) {
				_push(`<section${ssrRenderAttrs(mergeProps({
					class: "mt-6 rounded border border-slate-300 bg-white p-3 text-xs",
					"data-testid": "network-panel"
				}, _attrs))}><h2 class="mb-2 text-sm font-bold">Network panel (demo)</h2><p data-testid="initial-load"> Initial load (HTML): status <b>${ssrInterpolate(initial.value.status)}</b> · CDN <b>${ssrInterpolate(initial.value.cdn)}</b></p><p class="my-1" data-testid="origin-hits"> ORIGIN hits — static pages: <b data-testid="origin-static">${ssrInterpolate(origin.value?.static ?? "…")}</b> · dynamic: <b data-testid="origin-dynamic">${ssrInterpolate(origin.value?.dynamic ?? "…")}</b><button class="ml-2 rounded border px-1" data-testid="reset-origin">reset</button></p>`);
				if (unref(network).errors.length) _push(`<p class="text-red-700" data-testid="inertia-errors">Inertia errors: ${ssrInterpolate(unref(network).errors.join(" | "))}</p>`);
				else _push(`<p class="text-green-700" data-testid="inertia-errors">Inertia errors: none</p>`);
				_push(`<table class="mt-2 w-full text-left" data-testid="requests"><thead><tr><th>#</th><th>method</th><th>URL on the wire</th><th>status</th><th>CDN</th><th>X-Inertia-Static (origin decision)</th><th>x-inertia on the wire</th><th>origin?</th><th>browser</th><th>CF→origin ms</th><th>ms</th></tr></thead><tbody><!--[-->`);
				ssrRenderList(unref(network).requests.slice(0, 12), (r) => {
					_push(`<tr class="${ssrRenderClass(r.cache === "HIT" ? "text-green-700" : "")}"><td>${ssrInterpolate(r.id)}</td><td>${ssrInterpolate(r.method)}</td><td class="break-all">${ssrInterpolate(r.url)}</td><td>${ssrInterpolate(r.status)}</td><td>${ssrInterpolate(r.cache)}</td><td>${ssrInterpolate(r.inertiaStatic)}</td><td>${ssrInterpolate(r.xInertiaOnWire ? "yes" : "NO")}</td><td>${ssrInterpolate(r.originHit ? "yes" : "no")}</td><td data-testid="browser-cache">${ssrInterpolate(r.browser)}</td><td>${ssrInterpolate(r.originMs)}</td><td>${ssrInterpolate(r.ms)}</td></tr>`);
				});
				_push(`<!--]--></tbody></table></section>`);
			} else _push(`<!---->`);
		};
	}
});
//#endregion
//#region resources/js/Components/NetworkPanel.vue
var _sfc_setup$8 = NetworkPanel_vue_vue_type_script_setup_true_lang_default.setup;
NetworkPanel_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/NetworkPanel.vue");
	return _sfc_setup$8 ? _sfc_setup$8(props, ctx) : void 0;
};
var NetworkPanel_default = NetworkPanel_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Layouts/Layout.vue?vue&type=script&setup=true&lang.ts
var Layout_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	__name: "Layout",
	__ssrInlineRender: true,
	setup(__props) {
		const page = usePage();
		const island = useStaticIsland("/api/me");
		const sharedProps = computed(() => {
			const { appName, auth, flash, csrf, errors } = page.props;
			return {
				appName,
				auth,
				flash,
				csrf,
				errors
			};
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<div${ssrRenderAttrs(mergeProps({ class: "mx-auto max-w-3xl p-6 font-sans" }, _attrs))}><header class="mb-6 flex items-center justify-between border-b pb-4"><strong data-testid="app-name">${ssrInterpolate(unref(page).props.appName ?? "— no appName —")}</strong><nav class="flex gap-3 text-sm">`);
			_push(ssrRenderComponent(unref(Link), {
				href: "/",
				"data-testid": "nav-home"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Home (static)`);
					else return [createTextVNode("Home (static)")];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(unref(Link), {
				href: "/about",
				"data-testid": "nav-about"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`About (static)`);
					else return [createTextVNode("About (static)")];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(unref(Link), {
				href: "/blog",
				"data-testid": "nav-blog"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Blog (isr)`);
					else return [createTextVNode("Blog (isr)")];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(unref(Link), {
				href: "/dashboard",
				"data-testid": "nav-dashboard"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Dashboard (dynamic)`);
					else return [createTextVNode("Dashboard (dynamic)")];
				}),
				_: 1
			}, _parent));
			_push(ssrRenderComponent(unref(Link), {
				href: "/login",
				"data-testid": "nav-login"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Login`);
					else return [createTextVNode("Login")];
				}),
				_: 1
			}, _parent));
			_push(`</nav></header><p class="mb-4 rounded bg-indigo-50 p-2 text-sm" data-testid="island"> Island /api/me: `);
			if (unref(island).loading.value) _push(`<span>loading…</span>`);
			else if (unref(island).data.value?.user) _push(`<span>Hello, ${ssrInterpolate(unref(island).data.value.user.name)} <button class="ml-2 underline" data-testid="logout">Sign out</button></span>`);
			else _push(`<span>guest</span>`);
			_push(`</p><main>`);
			ssrRenderSlot(_ctx.$slots, "default", {}, null, _push, _parent);
			_push(`</main>`);
			_push(ssrRenderComponent(NetworkPanel_default, null, null, _parent));
			_push(`<section class="mt-4 rounded border bg-white p-3 text-xs"><div><b>component:</b> <span data-testid="component">${ssrInterpolate(unref(page).component)}</span></div><div><b>url:</b> <span data-testid="url">${ssrInterpolate(unref(page).url)}</span></div><div><b>received props:</b> <span data-testid="prop-keys">${ssrInterpolate(Object.keys(unref(page).props).sort().join(", "))}</span></div><pre data-testid="shared-props">${ssrInterpolate(JSON.stringify(sharedProps.value, null, 1))}</pre></section></div>`);
		};
	}
});
//#endregion
//#region resources/js/Layouts/Layout.vue
var _sfc_setup$7 = Layout_vue_vue_type_script_setup_true_lang_default.setup;
Layout_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Layouts/Layout.vue");
	return _sfc_setup$7 ? _sfc_setup$7(props, ctx) : void 0;
};
var Layout_default = Layout_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Components/NewsletterForms.vue?vue&type=script&setup=true&lang.ts
var NewsletterForms_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	__name: "NewsletterForms",
	__ssrInlineRender: true,
	setup(__props) {
		usePage();
		const form = useForm({ email: "" });
		const resultA = ref("");
		const http = useHttp({ email: "" });
		const resultB = ref("");
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<section${ssrRenderAttrs(mergeProps({ class: "mt-6 grid gap-4 rounded border bg-white p-3 text-sm md:grid-cols-2" }, _attrs))}><form data-testid="form-a"><h3 class="font-bold">A. useForm → return back()</h3><input${ssrRenderAttr("value", unref(form).email)} class="w-full border p-1" placeholder="email" data-testid="a-email"><p class="text-red-600" data-testid="a-error">${ssrInterpolate(unref(form).errors.email ?? "")}</p><p class="text-green-700" data-testid="a-flash">${ssrInterpolate(_ctx.$page.flash?.newsletter ?? "")}</p><p data-testid="a-result">${ssrInterpolate(resultA.value)}</p><button class="mt-1 rounded bg-slate-800 px-3 py-1 text-white" data-testid="a-submit">Subscribe</button></form><form data-testid="form-b"><h3 class="font-bold">B. useHttp → JSON</h3><input${ssrRenderAttr("value", unref(http).email)} class="w-full border p-1" placeholder="email" data-testid="b-email"><p class="text-red-600" data-testid="b-error">${ssrInterpolate(unref(http).errors.email ?? "")}</p><p class="text-green-700" data-testid="b-result">${ssrInterpolate(resultB.value)}</p><button class="mt-1 rounded bg-slate-800 px-3 py-1 text-white" data-testid="b-submit">Subscribe</button></form></section>`);
		};
	}
});
//#endregion
//#region resources/js/Components/NewsletterForms.vue
var _sfc_setup$6 = NewsletterForms_vue_vue_type_script_setup_true_lang_default.setup;
NewsletterForms_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Components/NewsletterForms.vue");
	return _sfc_setup$6 ? _sfc_setup$6(props, ctx) : void 0;
};
var NewsletterForms_default = NewsletterForms_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Pages/About.vue?vue&type=script&setup=true&lang.ts
var About_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	layout: Layout_default,
	__name: "About",
	__ssrInlineRender: true,
	props: { renderedAt: {} },
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "About" }, null, _parent));
			_push(`<h1 class="text-2xl font-bold">About (static)</h1><p>Rendered at the origin at <span data-testid="rendered-at">${ssrInterpolate(__props.renderedAt)}</span></p>`);
			_push(ssrRenderComponent(NewsletterForms_default, null, null, _parent));
			_push(`<!--]-->`);
		};
	}
});
//#endregion
//#region resources/js/Pages/About.vue
var About_exports = /* @__PURE__ */ __exportAll({ default: () => About_default });
var _sfc_setup$5 = About_vue_vue_type_script_setup_true_lang_default.setup;
About_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/About.vue");
	return _sfc_setup$5 ? _sfc_setup$5(props, ctx) : void 0;
};
var About_default = About_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Pages/Blog/Index.vue?vue&type=script&setup=true&lang.ts
var Index_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	layout: Layout_default,
	__name: "Index",
	__ssrInlineRender: true,
	props: {
		renderedAt: {},
		posts: {}
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Blog" }, null, _parent));
			_push(`<h1 class="text-2xl font-bold">Blog (ISR) — page ${ssrInterpolate(__props.posts.current_page)}</h1><p>Rendered at the origin at <span data-testid="rendered-at">${ssrInterpolate(__props.renderedAt)}</span></p><ul class="my-3 list-disc pl-6"><!--[-->`);
			ssrRenderList(__props.posts.data, (post) => {
				_push(`<li>`);
				_push(ssrRenderComponent(unref(Link), {
					href: `/blog/${post.slug}`,
					"data-testid": `post-${post.slug}`
				}, {
					default: withCtx((_, _push, _parent, _scopeId) => {
						if (_push) _push(`${ssrInterpolate(post.title)}`);
						else return [createTextVNode(toDisplayString(post.title), 1)];
					}),
					_: 2
				}, _parent));
				_push(`</li>`);
			});
			_push(`<!--]--></ul><div class="flex gap-3">`);
			if (__props.posts.current_page > 1) _push(ssrRenderComponent(unref(Link), {
				href: `/blog?page=${__props.posts.current_page - 1}`,
				"data-testid": "prev"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`← previous`);
					else return [createTextVNode("← previous")];
				}),
				_: 1
			}, _parent));
			else _push(`<!---->`);
			if (__props.posts.current_page < __props.posts.last_page) _push(ssrRenderComponent(unref(Link), {
				href: `/blog?page=${__props.posts.current_page + 1}`,
				"data-testid": "next"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`next →`);
					else return [createTextVNode("next →")];
				}),
				_: 1
			}, _parent));
			else _push(`<!---->`);
			_push(`</div><!--]-->`);
		};
	}
});
//#endregion
//#region resources/js/Pages/Blog/Index.vue
var Index_exports = /* @__PURE__ */ __exportAll({ default: () => Index_default });
var _sfc_setup$4 = Index_vue_vue_type_script_setup_true_lang_default.setup;
Index_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Blog/Index.vue");
	return _sfc_setup$4 ? _sfc_setup$4(props, ctx) : void 0;
};
var Index_default = Index_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Pages/Blog/Show.vue?vue&type=script&setup=true&lang.ts
var Show_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	layout: Layout_default,
	__name: "Show",
	__ssrInlineRender: true,
	props: {
		renderedAt: {},
		canonical: {},
		post: {}
	},
	setup(__props) {
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: __props.post.title }, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`<meta name="description"${ssrRenderAttr("content", __props.post.body)}${_scopeId}><meta property="og:title"${ssrRenderAttr("content", __props.post.title)}${_scopeId}><meta property="og:type" content="article"${_scopeId}><link rel="canonical"${ssrRenderAttr("href", __props.canonical)}${_scopeId}>`);
					else return [
						createVNode("meta", {
							name: "description",
							content: __props.post.body
						}, null, 8, ["content"]),
						createVNode("meta", {
							property: "og:title",
							content: __props.post.title
						}, null, 8, ["content"]),
						createVNode("meta", {
							property: "og:type",
							content: "article"
						}),
						createVNode("link", {
							rel: "canonical",
							href: __props.canonical
						}, null, 8, ["href"])
					];
				}),
				_: 1
			}, _parent));
			_push(`<h1 class="text-2xl font-bold" data-testid="post-title">${ssrInterpolate(__props.post.title)}</h1><p>${ssrInterpolate(__props.post.body)}</p><p>Rendered at the origin at <span data-testid="rendered-at">${ssrInterpolate(__props.renderedAt)}</span></p>`);
			_push(ssrRenderComponent(unref(Link), {
				href: "/blog",
				"data-testid": "back"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`← back to the blog`);
					else return [createTextVNode("← back to the blog")];
				}),
				_: 1
			}, _parent));
			_push(`<!--]-->`);
		};
	}
});
//#endregion
//#region resources/js/Pages/Blog/Show.vue
var Show_exports = /* @__PURE__ */ __exportAll({ default: () => Show_default });
var _sfc_setup$3 = Show_vue_vue_type_script_setup_true_lang_default.setup;
Show_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Blog/Show.vue");
	return _sfc_setup$3 ? _sfc_setup$3(props, ctx) : void 0;
};
var Show_default = Show_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Pages/Dashboard.vue?vue&type=script&setup=true&lang.ts
var Dashboard_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	layout: Layout_default,
	__name: "Dashboard",
	__ssrInlineRender: true,
	props: {
		renderedAt: {},
		secret: {},
		firstPost: {}
	},
	setup(__props) {
		const form = useForm({ title: __props.firstPost?.title ?? "" });
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Dashboard" }, null, _parent));
			_push(`<h1 class="text-2xl font-bold">Dashboard (dynamic)</h1>`);
			if (_ctx.$page.props.flash) _push(`<p class="text-green-700" data-testid="flash">${ssrInterpolate(_ctx.$page.props.flash)}</p>`);
			else _push(`<!---->`);
			_push(`<p data-testid="secret">${ssrInterpolate(__props.secret)}</p><p>Rendered at the origin at <span data-testid="rendered-at">${ssrInterpolate(__props.renderedAt)}</span></p>`);
			if (__props.firstPost) _push(`<form class="mt-4 flex gap-2"><input${ssrRenderAttr("value", unref(form).title)} class="flex-1 border p-1" data-testid="post-title-input"><button class="rounded bg-slate-800 px-3 text-white" data-testid="rename">Rename post ${ssrInterpolate(__props.firstPost.id)}</button></form>`);
			else _push(`<!---->`);
			_push(ssrRenderComponent(NewsletterForms_default, null, null, _parent));
			_push(`<!--]-->`);
		};
	}
});
//#endregion
//#region resources/js/Pages/Dashboard.vue
var Dashboard_exports = /* @__PURE__ */ __exportAll({ default: () => Dashboard_default });
var _sfc_setup$2 = Dashboard_vue_vue_type_script_setup_true_lang_default.setup;
Dashboard_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Dashboard.vue");
	return _sfc_setup$2 ? _sfc_setup$2(props, ctx) : void 0;
};
var Dashboard_default = Dashboard_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Pages/Home.vue?vue&type=script&setup=true&lang.ts
var Home_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	layout: Layout_default,
	__name: "Home",
	__ssrInlineRender: true,
	props: {
		renderedAt: {},
		preview: { type: Boolean }
	},
	setup(__props) {
		const { start, stop, polling } = usePoll(1500, { only: ["renderedAt"] }, { autoStart: false });
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Home" }, null, _parent));
			if (__props.preview) _push(`<p class="mb-2 rounded bg-amber-100 p-2 text-sm" data-testid="preview-banner"> Preview mode: fresh content, not cached. <a href="/preview/disable" class="underline" data-testid="preview-exit">Exit</a></p>`);
			else _push(`<!---->`);
			_push(`<h1 class="text-2xl font-bold">Static home</h1><p>Rendered at the origin at <span data-testid="rendered-at">${ssrInterpolate(__props.renderedAt)}</span></p><div class="mt-3 flex gap-3 text-sm">`);
			_push(ssrRenderComponent(unref(Link), {
				href: "/blog/post-11",
				prefetch: "",
				"data-testid": "prefetch-link",
				class: "underline"
			}, {
				default: withCtx((_, _push, _parent, _scopeId) => {
					if (_push) _push(`Post 11 (prefetch on hover)`);
					else return [createTextVNode("Post 11 (prefetch on hover)")];
				}),
				_: 1
			}, _parent));
			if (!unref(polling)) _push(`<button class="rounded border px-2" data-testid="poll-start">Start polling</button>`);
			else _push(`<button class="rounded border px-2" data-testid="poll-stop">Stop polling</button>`);
			_push(`</div><!--]-->`);
		};
	}
});
//#endregion
//#region resources/js/Pages/Home.vue
var Home_exports = /* @__PURE__ */ __exportAll({ default: () => Home_default });
var _sfc_setup$1 = Home_vue_vue_type_script_setup_true_lang_default.setup;
Home_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Home.vue");
	return _sfc_setup$1 ? _sfc_setup$1(props, ctx) : void 0;
};
var Home_default = Home_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/Pages/Login.vue?vue&type=script&setup=true&lang.ts
var Login_vue_vue_type_script_setup_true_lang_default = /*@__PURE__*/ defineComponent({
	layout: Layout_default,
	__name: "Login",
	__ssrInlineRender: true,
	setup(__props) {
		const form = useForm({
			email: "alice@example.com",
			password: "password"
		});
		return (_ctx, _push, _parent, _attrs) => {
			_push(`<!--[-->`);
			_push(ssrRenderComponent(unref(Head), { title: "Login" }, null, _parent));
			_push(`<h1 class="text-2xl font-bold">Login (dynamic)</h1><form class="mt-3 flex flex-col gap-2"><input${ssrRenderAttr("value", unref(form).email)} class="border p-1" data-testid="email"><input${ssrRenderAttr("value", unref(form).password)} type="password" class="border p-1" data-testid="password">`);
			if (unref(form).errors.email) _push(`<p class="text-red-600">${ssrInterpolate(unref(form).errors.email)}</p>`);
			else _push(`<!---->`);
			_push(`<button type="submit" class="rounded bg-slate-800 p-2 text-white" data-testid="submit">Sign in</button></form><!--]-->`);
		};
	}
});
//#endregion
//#region resources/js/Pages/Login.vue
var Login_exports = /* @__PURE__ */ __exportAll({ default: () => Login_default });
var _sfc_setup = Login_vue_vue_type_script_setup_true_lang_default.setup;
Login_vue_vue_type_script_setup_true_lang_default.setup = (props, ctx) => {
	const ssrContext = useSSRContext();
	(ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/Pages/Login.vue");
	return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
var Login_default = Login_vue_vue_type_script_setup_true_lang_default;
//#endregion
//#region resources/js/ssr.ts
createServer((page) => createInertiaApp({
	page,
	render: renderToString,
	resolve: (name) => {
		return (/* @__PURE__ */ Object.assign({
			"./Pages/About.vue": About_exports,
			"./Pages/Blog/Index.vue": Index_exports,
			"./Pages/Blog/Show.vue": Show_exports,
			"./Pages/Dashboard.vue": Dashboard_exports,
			"./Pages/Home.vue": Home_exports,
			"./Pages/Login.vue": Login_exports
		}))[`./Pages/${name}.vue`];
	},
	setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin)
}));
//#endregion
export {};
