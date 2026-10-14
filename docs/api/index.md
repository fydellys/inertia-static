# API Reference

Every public function, in the format of the Next.js API reference. Each page has the signature, the parameters,
what it returns, examples and the things that are good to know.

## Route configuration

| API | Next.js equivalent |
|---|---|
| [`->isr()` / `->static()` / `->dynamic()`](./route-isr) | `export const revalidate`, `dynamic = 'force-static'` |
| [`#[StaticPage]`](./static-page-attribute) | route segment config |
| [`StaticPathsProvider`](./static-paths-provider) | `generateStaticParams` / `getStaticPaths` |

## Responses

| API | Next.js equivalent |
|---|---|
| [`->revalidate()`, `->staleWhileRevalidate()`, `->staleIfError()`](./response-revalidate) | `revalidate` in `fetch` / `cacheLife` |
| [`->tags()`](./response-tags) | `next: { tags }` / `cacheTag` |

## On-demand revalidation

| API | Next.js equivalent |
|---|---|
| [`StaticCache::purgeTags()`](./purge-tags) | `revalidateTag()` |
| [`StaticCache::purgeUrls()`](./purge-urls) | `revalidatePath()` |
| [`StaticCache::purgePrefix()`](./purge-prefix) | `revalidatePath('/blog', 'layout')` |
| [`StaticCache::purgeAll()`](./purge-all) | `revalidatePath('/', 'layout')` |
| [`StaticCache::purgeNow()`](./purge-now) | — (synchronous variant) |
| [`StaticCache::canPurge()`](./can-purge) | — |
| [`HasStaticTags`](./has-static-tags) | — (automatic purges from Eloquent) |

## Client

| API | Next.js equivalent |
|---|---|
| [`inertiaStatic()`](./inertia-static) | the App Router's RSC/data fetching |
| [`useStaticIsland()`](./use-static-island) | a client component fetching user data |
| [`ensureCsrfCookie()`](./ensure-csrf-cookie) | — |
| [Vite plugin](./vite-plugin) | — |
