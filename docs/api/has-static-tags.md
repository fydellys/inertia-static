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
$post->update(['title' => 'New title']);   // → StaticCache::purgeTags(['posts', 'post:1'])
```

## Reference

```php
public function staticTags(): array   // default: [$this->getTable(), Str::singular($this->getTable()).':'.$this->getKey()]
```

## Examples

### Custom tags

```php
public function staticTags(): array
{
    return ['posts', "post:{$this->id}", "author:{$this->author_id}", "category:{$this->category_id}"];
}
```

Tag the pages the same way:

```php
return Inertia::render('Post/Show', [...])->tags(["post:{$post->id}", "author:{$post->author_id}"]);
```

## Good to know

- Ten saves of the same model inside the debounce window produce **one** purge.
- The purge is queued after the transaction commits: a rolled back change purges nothing.
- Verified end to end on a real Cloudflare zone: rename a post → HTML, page JSON and the `posts` listing refreshed.
