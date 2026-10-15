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

Tags from the route (`->isr(tags: [...])`) and from the response are merged. Calling it several times adds tags.

## Good to know

- Pick tags from the data the page shows: a page listing posts gets `posts`; a page showing post 42 gets `post:42`.
- [`HasStaticTags`](./has-static-tags) purges `[table, singular:id]` by default (`posts`, `post:42`). Use the same
  names here.
- Only applies to `isr` and `static` routes.
- CDNs without cache tags (Bunny, CloudFront, nginx) need the [tag registry](../guide/revalidation.md#tag-registry)
  to purge by tag.
- The tag header is capped by the CDN's size limit (16 KB on Cloudflare and Fastly); tags beyond it are dropped.
