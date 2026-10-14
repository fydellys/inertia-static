# Quick start

## 1. Declare static routes

```php
// routes/web.php
use App\Http\Controllers\PostController;
use App\StaticPaths\PostPaths;

Route::get('/', HomeController::class)->static(revalidate: 600);

Route::get('/blog', [PostController::class, 'index'])
    ->isr(revalidate: 300, tags: ['posts'], query: ['page']);

Route::get('/blog/{post:slug}', [PostController::class, 'show'])
    ->isr(revalidate: 3600, paths: PostPaths::class, tags: ['posts']);

Route::get('/dashboard', DashboardController::class)->middleware('auth'); // dynamic
```

## 2. Tag your responses

```php
public function show(Post $post)
{
    return Inertia::render('Post/Show', ['post' => $post])
        ->tags(["post:{$post->id}"]);
}
```

## 3. Purge when data changes

```php
use Fydellys\InertiaStatic\Concerns\HasStaticTags;

class Post extends Model
{
    use HasStaticTags; // saved / deleted / restored => purge ["posts", "post:{id}"]
}
```

## 4. Keep shared props public

```php
// config/inertia-static.php
'public_shared_props' => ['appName'],
```

`auth`, `flash` and everything else shared by `HandleInertiaRequests` is dropped on static routes. Load user data
in the browser with an [island](./islands.md):

```vue
<script setup lang="ts">
import { useStaticIsland } from '@fydellys/inertia-static/vue'
const { data: me } = useStaticIsland<{ user: { name: string } | null }>('/api/me')
</script>

<template>
  <span v-if="me?.user">Hello, {{ me.user.name }}</span>
</template>
```

## 5. Install the client plugin and check

```bash
php artisan inertia-static:manifest && npm run build
php artisan inertia-static:routes
php artisan inertia-static:doctor
curl -I https://example.com/blog
```

```http
HTTP/2 200
cache-control: public, max-age=0, must-revalidate
cloudflare-cdn-cache-control: public, max-age=300, stale-while-revalidate=86400, stale-if-error=604800
cache-tag: posts
etag: "9f2c0d…"
x-inertia-static-cache: cacheable
x-inertia-static: MISS; ttl=300
```
