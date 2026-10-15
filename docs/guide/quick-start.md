# Quick start

Install the package and the client first ([Installation](./installation.md)).

## 1. Declare static routes

```php
// routes/web.php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PostController;
use App\StaticPaths\PostPaths;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->static(revalidate: 600);

Route::get('/blog', [PostController::class, 'index'])
    ->isr(revalidate: 300, tags: ['posts'], query: ['page']);

Route::get('/blog/{post:slug}', [PostController::class, 'show'])
    ->isr(revalidate: 3600, paths: PostPaths::class, tags: ['posts']);

Route::get('/dashboard', DashboardController::class)->middleware('auth'); // dynamic
```

`PostPaths` lists the posts to pre-render ([Static paths](./static-paths.md)). Routes without a mode stay dynamic.

## 2. Tag your responses

Add per-page tags in the controller:

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

## 5. Build and check

```bash
npm run build      # the Vite plugin writes the manifest first
php artisan inertia-static:routes
php artisan inertia-static:doctor
curl -I https://example.com/blog
```

With the `cloudflare` driver:

```http
HTTP/2 200
cache-control: public, max-age=0, must-revalidate
cloudflare-cdn-cache-control: public, max-age=300, stale-while-revalidate=31535700, stale-if-error=604800
cache-tag: posts,_IST_/,_IST_/blog/
etag: "9f2c0d…"
x-inertia-static-cache: cacheable
x-inertia-static: MISS
```

## Next

Set up your CDN so it stores the pages: [Choosing a driver](../cdn/overview.md).
