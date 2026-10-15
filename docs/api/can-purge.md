# `StaticCache::canPurge()`

Whether the CDN driver can purge. `false` when it has no credentials ([headers-only mode](../guide/headers-only.md)).

```php
if (! StaticCache::canPurge()) {
    // pages expire by TTL only
}
```

## Reference

```php
StaticCache::canPurge(): bool
```

## Good to know

- In headers-only mode, `purgeTags()`, `purgeUrls()`, `purgePrefix()` and `purgeAll()` do nothing and log a notice.
