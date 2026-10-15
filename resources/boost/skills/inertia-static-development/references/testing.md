# Testing static routes

The leak detector throws `StaticRenderViolation` in the `testing` environment (`leak_detector.throw_in`), so a
feature test that requests each static route as a guest fails when the render reads the user, session, cookies or
auth headers.

```php
it('serves the blog index as a cacheable static page', function () {
    $this->get('/blog')
        ->assertOk()
        ->assertHeader('X-Inertia-Static', 'MISS')
        ->assertHeader('X-Inertia-Static-Cache', 'cacheable');
});
```

- A `bypass` response carries `X-Inertia-Static-Reason` (values in troubleshooting.md). If Inertia SSR is enabled in
  the test environment without an SSR server, the reason is `ssr-unavailable`: disable Inertia SSR in tests or set
  `INERTIA_STATIC_SSR_REQUIRED=false`.
- Non-whitelisted query parameters return a `301` to the canonical URL; assert with `assertRedirect()`.

## Purges

With the default `null` driver, purges are queued as `Fydellys\InertiaStatic\Jobs\PurgeStaticCache`. Identical purges
inside `purge.debounce` are collapsed into one job.

```php
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Jobs\PurgeStaticCache;
use Illuminate\Support\Facades\Queue;

it('purges the post tags when a post changes', function () {
    Queue::fake();

    $post = Post::factory()->create();
    $post->update(['title' => 'New title']);

    Queue::assertPushed(PurgeStaticCache::class, fn (PurgeStaticCache $job) => $job->operation === PurgeOperation::Tags
        && in_array("post:{$post->id}", $job->items, true));
});
```

## Islands

Island endpoints are ordinary dynamic routes: test them with `actingAs($user)->getJson('/api/me')`.

## CI

Run `php artisan inertia-static:doctor`; it exits non-zero when a check fails.
