# `StaticCache::canPurge()`

`false` when the CDN driver has no credentials ([headers-only mode](../guide/headers-only.md)).

```php
if (! StaticCache::canPurge()) {
    // pages expire by TTL only
}
```

## Reference

```php
StaticCache::canPurge(): bool
```
