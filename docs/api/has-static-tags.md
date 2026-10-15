# `HasStaticTags`

An Eloquent trait that purges the model's cache tags whenever it is saved, deleted or restored.

```php
use Fydellys\InertiaStatic\Concerns\HasStaticTags;

class Post extends Model
{
    use HasStaticTags;
}
```

```php
$post->update(['title' => 'New title']);   // purges the tags 'posts' and 'post:1'
```

## Reference

```php
// Override to choose the tags. Default: [table, singular table:key]
public function staticTags(): array
```

Custom tags:

```php
public function staticTags(): array
{
    return ['posts', "post:{$this->id}", "author:{$this->author_id}"];
}
```

## Good to know

- Tag the pages with the same names, with [`->tags()`](./response-tags) or the route's `tags:` option.
- It calls [`StaticCache::purgeTags()`](./purge-tags): the purge waits for the database transaction to commit, so a
  rolled back change purges nothing, and repeated saves within `purge.debounce` seconds produce one purge.
