<?php

namespace App\StaticPaths;

use App\Models\Post;
use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;

/**
 * Lists the concrete pages of the route  /blog/{post:slug}.
 *
 * Attached in routes/web.php:
 *
 *     Route::get('/blog/{post:slug}', [PageController::class, 'post'])
 *         ->name('blog.show')
 *         ->isr(paths: PostPaths::class);
 *
 * Each yielded array maps a ROUTE PARAMETER NAME to its value in the URL. For {post:slug} the key is
 * "post" (the parameter), the value is the slug:  ['post' => 'post-1']  →  /blog/post-1
 *
 * It is never called on regular requests. Artisan uses it:
 *
 *     php artisan inertia-static:routes                                 → "Paths" column
 *     php artisan inertia-static:warm --only=blog.show --dry-run        → lists the URLs (HTML + page JSON)
 *     php artisan inertia-static:warm --only=blog.show                  → pre-fills the CDN: first visits are HITs
 *     php artisan inertia-static:purge --all --warm                     → deploy hook: purge, then refill
 *
 * With ->isr() it is optional (unlisted posts are rendered and cached on their first visit).
 * With ->static() and parameters it is required (every page is known in advance).
 */
class PostPaths implements StaticPathsProvider
{
    public function paths(): iterable
    {
        // lazy(): one query per chunk, never the whole table in memory.
        foreach (Post::query()->orderBy('id')->lazy() as $post) {
            yield ['post' => $post->slug];
        }
    }
}
