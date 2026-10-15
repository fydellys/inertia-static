<?php

use Fydellys\InertiaStatic\Facades\StaticCache;
use Fydellys\InertiaStatic\Tests\Fixtures\Http\AttributeController;
use Fydellys\InertiaStatic\Tests\Fixtures\PostPaths;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('web')->group(function () {
    Route::get('/', fn () => Inertia::render('Home', ['title' => 'Home']))
        ->name('home')
        ->static(revalidate: 600);

    Route::get('/blog', fn () => Inertia::render('Blog/Index', ['title' => 'Blog', 'page' => request('page', 1)]))
        ->name('blog.index')
        ->isr(revalidate: 300, tags: ['posts'], query: ['page']);

    Route::get('/blog/{slug}', fn (string $slug) => Inertia::render('Blog/Show', ['title' => "Post {$slug}"])
        ->revalidate(3600)
        ->staleWhileRevalidate(60)
        ->tags(["post:{$slug}"]))
        ->name('blog.show')
        ->isr(paths: PostPaths::class, tags: ['posts']);

    Route::get('/dashboard', fn () => Inertia::render('Dashboard', ['title' => 'Dashboard']))
        ->name('dashboard');

    Route::get('/attribute', [AttributeController::class, 'show'])->name('attribute');

    // Pages misbehaving on purpose (leak detector).
    Route::get('/leak/session', fn () => Inertia::render('Leak', ['value' => session('key')]))->isr();
    Route::get('/leak/auth', fn () => Inertia::render('Leak', ['value' => auth()->check()]))->isr();
    // The user read only by a prop a partial reload asks for (<WhenVisible>), never by the cached render.
    Route::get('/leak/optional', fn () => Inertia::render('Leak', ['value' => 'public', 'me' => Inertia::optional(fn () => auth()->user()?->name ?? 'guest')]))->isr();
    Route::get('/leak/auth-facade', fn () => Inertia::render('Leak', ['value' => Auth::id()]))->isr();
    Route::get('/leak/request-user', fn (Request $request) => Inertia::render('Leak', ['value' => $request->user()]))->isr();
    Route::get('/leak/cookie', function () {
        Cookie::queue('theme', 'dark');

        return Inertia::render('Leak');
    })->isr();
    Route::get('/read-cookie', fn (Request $request) => Inertia::render('Cookie', ['theme' => $request->cookie('theme')]))->isr();
    Route::get('/leak/header', fn (Request $request) => Inertia::render('Leak', ['value' => $request->bearerToken()]))->isr();
    Route::get('/leak/set-cookie', fn () => response()->json(['ok' => true])->cookie('tracking', 'abc'))->isr();

    Route::get('/devtools', fn () => response()->json(['ok' => true])->header('X-Inertia-Devtools-Id', 'abc'))->isr();
    Route::get('/preview-check', fn () => Inertia::render('Home', ['preview' => StaticCache::isPreview()]))->isr();
    Route::get('/broken', fn () => abort(500))->isr();
    Route::get('/boom', fn () => throw new RuntimeException('boom'))->isr();
    Route::get('/absolute', fn () => Inertia::render('Home', ['asset' => url('/build/app.js')]))->isr();
    Route::get('/missing', fn () => abort(404))->isr();
    Route::get('/moved', fn () => redirect('/'))->isr();
    Route::get('/deferred', fn () => Inertia::render('Deferred', ['slow' => Inertia::defer(fn () => 'x')]))->isr();
});
