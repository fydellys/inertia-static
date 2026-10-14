# `->tags()`

Attach cache tags to one response, so [`StaticCache::purgeTags()`](./purge-tags) can purge it later.

```php
return Inertia::render('Post/Show', ['post' => $post])
    ->tags(['posts', "post:{$post->id}", "author:{$post->author_id}"]);
```

## Reference

```php
Inertia\Response::tags(array|string $tags): Inertia\Response
```

Tags from the route (`->isr(tags: [...])`) and from the response are merged.

## Where tags go

| Driver | Header | Purge |
|---|---|---|
| Cloudflare | `Cache-Tag: posts,post:1` | native tag purge |
| Fastly | `Surrogate-Key: posts post:1` | native key purge |
| Varnish | `xkey: posts post:1` | xkey soft purge |
| nginx / Bunny | — | [tag registry](../guide/revalidation.md#tag-registry) → URL purges |

## Good to know

- Pick tags from the data the page shows: a page listing posts gets `posts`; a page showing post 42 gets `post:42`.
- [`HasStaticTags`](./has-static-tags) purges `[table, singular:id]` by default. Use the same names here.
- Cloudflare: up to 1,024 characters per tag and 16 KB per header. Tags beyond the limit are dropped from the header.
