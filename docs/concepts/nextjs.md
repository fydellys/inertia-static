# Compared to Next.js

| Next.js | Inertia Static |
|---|---|
| `export const revalidate = 60` / `getStaticProps({ revalidate })` | `->isr(revalidate: 60)` on the route, or `->revalidate(60)` on the Inertia response |
| `expireTime` / `cacheLife({ revalidate, expire })` | `revalidate` + `expire` (`stale-while-revalidate = expire - revalidate`) |
| `generateStaticParams` / `getStaticPaths` | a `StaticPathsProvider` class (`paths:`) |
| `revalidateTag('posts')` | `StaticCache::purgeTags('posts')` |
| `revalidatePath('/blog')` | `StaticCache::purgeUrls('/blog')` / `StaticCache::purgePrefix('/blog')` |
| `revalidateTag(tag, 'max')` / `updateTag(tag)` | `PurgeMode::Soft` / `PurgeMode::Hard` (`StaticCache::purgeTags($tag, PurgeMode::Hard)`) |
| implicit `_N_T_` path tags | implicit `_IST_/…/` path tags |
| `draftMode()` | `StaticCache::enablePreview()` / `StaticCache::isPreview()` |
| `cookies()` / `headers()` opt a route out of static | leak detector: `throw` in development, `auto` in production (the route becomes dynamic) |
| `/_next/data/{buildId}/{path}.json` | `/__inertia/{assetVersion}/{path}.json` |
| `buildId` / deployment skew protection | Inertia asset version in the JSON URL + `409` full reload |
| `x-nextjs-cache: HIT/MISS/STALE` | `X-Inertia-Static: MISS` (origin decision) + your CDN's status header |
| incremental cache on the origin | the CDN is the cache; the origin only renders |

The main difference: Next.js revalidation invalidates its own origin-side cache, and `revalidateTag` /
`revalidatePath` do not purge your CDN. Inertia Static has no origin cache: the CDN is the cache, so purges go
straight to the CDN, through a queue with batching and rate limits.

## Public internal routes

| Next.js | Purpose | Inertia Static |
|---|---|---|
| `/_next/data/{buildId}/{path}.json` | page data (Pages Router) | `/__inertia/{assetVersion}/{path}.json` |
| `/{path}.rsc`, `/{path}.segments/…` | RSC payload / segment prefetch (App Router) | the same page JSON URL |
| `/{path}?_rsc={hash}` + `RSC: 1` | dynamic navigation | `/__inertia/live/{assetVersion}/{path}.json` (never cached) |
| `/_next/image?url=…` | image optimizer | — |
| `POST {page}` + `Next-Action` | Server Actions | your regular Laravel routes |

Like Next.js with `_rsc`, the page JSON gets its own URL instead of relying on the `Vary` header, which some CDNs
ignore. The JSON URL only answers `GET` for `isr` / `static` routes (`404` otherwise) and returns what the public
HTML page already shows; only the never-cached live URL reads the session, to return the visitor's own form errors
and flash data.
