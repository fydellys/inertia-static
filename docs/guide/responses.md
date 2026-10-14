# Responses & tags

`Inertia\Response` gets macros to tune the policy of a single response:

```php
return Inertia::render('Post/Show', ['post' => $post])
    ->revalidate(3600)            // edge TTL for this response
    ->staleWhileRevalidate(86400)
    ->staleIfError(604800)
    ->tags(['posts', "post:{$post->id}"]);
```

They override the route options for the current request only. On dynamic routes they are ignored.

## Tags

Tags are sent in the provider's tag header (`Cache-Tag`, `Surrogate-Key`, `xkey`…) and recorded in the
[tag registry](./revalidation.md#tag-registry) when one is enabled. Purging a tag purges **both** URLs of every
page carrying it: the HTML and its page JSON.

Good tag design:

| Tag | Purged when |
|---|---|
| `posts` | any post changes (listings, archives, home widgets) |
| `post:42` | post 42 changes (its page) |
| `author:7` | author 7 changes (bio box on every post) |

`HasStaticTags` uses `[table, singular:id]` by default (`posts`, `post:42`). Override `staticTags()` to change it.

## What is cached

The [policy resolver](../concepts/how-it-works.md#fail-safe-cache-policy) decides. In short:

| Response | Cached? |
|---|---|
| `200` HTML rendered with SSR | yes, `revalidate` |
| `200` page JSON (`/__inertia/...`) | yes, `revalidate` |
| `404` | yes, `defaults.not_found_ttl` (60s) |
| canonical `301` (query / trailing slash) | yes, `defaults.redirect_ttl` |
| other redirects | only with `defaults.cache_redirects` |
| `5xx`, `409`, anything else | never |
| HTML without SSR | never (unless `ssr.required` is false) |
| requests with `Authorization`, partial reloads, leaks | never |
