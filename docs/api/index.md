# API Reference

Every public function: signature, parameters, return value, examples and what is good to know.

## Route configuration

- [`->isr()` / `->static()` / `->dynamic()`](./route-isr)
- [`#[StaticPage]`](./static-page-attribute)
- [`StaticPathsProvider`](./static-paths-provider)

## Responses

- [`->revalidate()`, `->staleWhileRevalidate()`, `->staleIfError()`](./response-revalidate)
- [`->tags()`](./response-tags)

## On-demand revalidation

- [`StaticCache::purgeTags()`](./purge-tags)
- [`StaticCache::purgeUrls()`](./purge-urls)
- [`StaticCache::purgePrefix()`](./purge-prefix)
- [`StaticCache::purgeAll()`](./purge-all)
- [`StaticCache::purgeNow()`](./purge-now)
- [`StaticCache::canPurge()`](./can-purge)
- [`HasStaticTags`](./has-static-tags)

## Client

- [`inertiaStatic()`](./inertia-static) (Inertia 3)
- [`installInertiaStatic()`](./install-inertia-static) (Inertia 2)
- [`useStaticIsland()`](./use-static-island)
- [`ensureCsrfCookie()`](./ensure-csrf-cookie)
- [Vite plugin](./vite-plugin)
