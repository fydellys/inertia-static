<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inertia features used on a static (ISR) page: deferred, optional (WhenVisible),
 * infinite scroll, merge and once props, history encryption, remember, <Form>.
 */
class FeaturesController
{
    public function show(): Response
    {
        return $this->render('Features');
    }

    public function two(): Response
    {
        return $this->render('Features two');
    }

    /** Optimistic update target (Inertia 3). */
    public function like(): RedirectResponse
    {
        Cache::increment('features-likes');

        return back();
    }

    protected function render(string $title): Response
    {
        Inertia::encryptHistory();

        $at = fn (string $what) => $what.' at '.now()->format('H:i:s.v');

        return Inertia::render('Features', [
            'title' => $title,
            'renderedAt' => now()->format('H:i:s.v'),
            'deferred' => Inertia::defer(fn () => $at('deferred')),
            'visible' => Inertia::optional(fn () => $at('visible')),
            'posts' => Inertia::scroll(fn () => Post::query()->orderBy('id')->paginate(4)->through(fn (Post $post) => $post->only('id', 'title'))),
            'log' => Inertia::merge(fn () => [$at('entry')]),
            'plans' => Inertia::once(fn () => $at('plans')),
            'likes' => (int) Cache::get('features-likes', 0),
        ]);
    }
}
