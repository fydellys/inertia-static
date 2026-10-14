# Compared to Next.js

| Next.js | Inertia Static |
|---|---|
| `export const revalidate = 60` / `getStaticProps({ revalidate })` | `->isr(revalidate: 60)` / `->revalidate(60)` |
| `generateStaticParams` / `getStaticPaths` | `StaticPathsProvider` (`paths:`) |
| `revalidateTag('posts')` | `StaticCache::purgeTags(['posts'])` |
| `revalidatePath('/blog')` | `StaticCache::purgeUrls(['/blog'])` / `purgePrefix('/blog')` |
| `/_next/data/{buildId}/{path}.json` | `/__inertia/{assetVersion}/{path}.json` |
| `buildId` / deployment skew protection | Inertia asset version + 409 full reload |
| `x-nextjs-cache: HIT/MISS/STALE` | `X-Inertia-Static` (origin decision) + your CDN's status header |
| `cookies()` / `headers()` opt a route out of static | leak detector: throw in dev, `auto` in prod (the route becomes dynamic) |
| `expireTime` / `cacheLife({ revalidate, expire })` | `revalidate` + `expire` (`stale-while-revalidate = expire - revalidate`) |
| `revalidateTag(tag, 'max')` / `updateTag(tag)` | `PurgeMode::Soft` / `PurgeMode::Hard` |
| implicit `_N_T_` path tags | implicit `_IST_/…/` path tags |
| `draftMode()` | `StaticCache::enablePreview()` / `isPreview()` |
| `x-nextjs-cache: MISS` | `X-Inertia-Static: MISS` |
| incremental cache on the origin | the CDN is the cache; the origin only renders |

The main difference: Next.js invalidates its own origin-side cache and, by its own docs,
`revalidateTag`/`revalidatePath` **do not purge your CDN**. Inertia Static has no origin cache: the CDN is the
cache, so purges go straight to the CDN, with queues, batching and rate limits.

## Internal public routes

Next.js exposes several internal routes to the public, by design. The Next.js references below are checked against
its source (canary 16.5):

| Next.js | Purpose | inertia-static |
|---|---|---|
| `/_next/data/{buildId}/{path}.json` | page data, Pages Router | `/__inertia/{assetVersion}/{path}.json`, same design |
| `/{path}.rsc`, `/{path}.segments/…segment.rsc` | RSC payload / segment prefetch, App Router | same endpoint |
| `/{path}?_rsc={hash}` + `RSC: 1` | dynamic navigation; the server redirects when the hash does not match the headers | `/__inertia/live/{v}/{path}.json` (`no-store`); the cacheable URL depends on no header, and a non-canonical query gets a `301` |
| `/_next/image?url=…` | image optimizer | — |
| `POST {page}` + `Next-Action` | Server Actions (public POST endpoints) | your regular Laravel routes |

Next.js documents why it adds `_rsc`: *"Rather than relying on the Vary header which some CDNs ignore, we append a
search param to create a unique URL"*. This is the same reason the page JSON has its own URL here.

The JSON endpoint is narrower than `/_next/data`:

- it only answers `GET` for routes declared `isr`/`static` (`404` otherwise);
- it returns exactly what the public HTML page already shows;
- only the never-cached live endpoint reads the session, and only to return the requester's own form errors and
  flash.
