<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Inertia\Inertia;
use Inertia\Response;

class PageController
{
    public function home(): Response
    {
        return Inertia::render('Home', [
            'renderedAt' => now()->format('H:i:s.v'),
            'preview' => StaticCache::isPreview(), // drafts would be shown here
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('About', ['renderedAt' => now()->format('H:i:s.v')]);
    }

    public function blog(Request $request): Response
    {
        $posts = Post::query()->orderBy('id')->paginate(5);

        return Inertia::render('Blog/Index', [
            'posts' => $posts->through(fn (Post $post) => $post->only('slug', 'title')),
            'renderedAt' => now()->format('H:i:s.v'),
        ])->tags(['posts']);
    }

    public function post(Post $post): Response
    {
        return Inertia::render('Blog/Show', [
            'post' => $post->only('id', 'slug', 'title', 'body'),
            // Absolute URLs from APP_URL, never from the request Host (unkeyed header poisoning).
            'canonical' => rtrim((string) config('app.url'), '/')."/blog/{$post->slug}",
            'renderedAt' => now()->format('H:i:s.v'),
        ])->revalidate(120)->tags(['posts', "post:{$post->id}"]);
    }

    public function dashboard(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'firstPost' => Post::query()->orderBy('id')->first()?->only('id', 'title'),
            'secret' => "Balance of {$request->user()->name}: $1,234.56",
            'renderedAt' => now()->format('H:i:s.v'),
        ]);
    }

    public function rename(Request $request, Post $post): RedirectResponse
    {
        $post->update($request->validate(['title' => ['required', 'string', 'max:120']]));

        // HasStaticTags queues a purge of ["posts", "post:{id}"] (debounced).
        return back()->with('status', "Post {$post->id} renamed; purge queued.");
    }

    public function login(): Response
    {
        return Inertia::render('Login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Signed in!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()?->only('id', 'name')])
            ->header('Cache-Control', 'no-store, private');
    }
}
