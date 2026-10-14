<?php

use App\Http\Controllers\PageController;
use App\StaticPaths\PostPaths;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Inertia\Inertia;

// Static / ISR pages (shared by every visitor, cached by the CDN).
Route::get('/', [PageController::class, 'home'])->name('home')->static(revalidate: 300);
Route::get('/about', [PageController::class, 'about'])->name('about')->static(revalidate: 10);
Route::get('/blog', [PageController::class, 'blog'])->name('blog.index')->isr(revalidate: 60, query: ['page']);
Route::get('/blog/{post:slug}', [PageController::class, 'post'])->name('blog.show')->isr(paths: PostPaths::class);

// Dynamic pages (session, auth, flash).
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::post('/login', [PageController::class, 'authenticate']);
Route::post('/logout', [PageController::class, 'logout'])->name('logout');
Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard')->middleware('auth');
Route::post('/admin/posts/{post}', [PageController::class, 'rename'])->name('posts.rename')->middleware('auth');

// Misbehaving static page: reads the logged-in user (leak detector demo).
Route::get('/leak', fn () => inertia('Home', ['renderedAt' => auth()->user()?->name ?? 'anon']))->isr();

// Newsletter: the same form submitted two ways (see NewsletterForms.vue).
Route::post('/newsletter', function (Request $request) {
    $data = $request->validate(['email' => ['required', 'email']]);

    // Inertia 3 flash data: delivered to static pages too (through the live endpoint).
    Inertia::flash('newsletter', "Subscribed {$data['email']}");

    return back();
})->name('newsletter');

Route::post('/api/newsletter', function (Request $request) {
    $data = $request->validate(['email' => ['required', 'email']]);

    return response()->json(['message' => "Subscribed {$data['email']}"]);
})->name('api.newsletter');

// Preview mode: only signed-in users can turn it on.
Route::get('/preview/enable', fn () => redirect('/')->withCookie(StaticCache::enablePreview()))->middleware('auth')->name('preview.enable');
Route::get('/preview/disable', fn () => redirect('/')->withCookie(StaticCache::disablePreview()))->name('preview.disable');

// Dynamic "island" consumed by static pages in the browser.
Route::get('/api/me', [PageController::class, 'me'])->name('me');

// Demo only: origin hit counter (never cached).
Route::get('/__origin/stats', fn () => cache('origin-hits', ['static' => 0, 'dynamic' => 0, 'log' => []]));
Route::get('/__origin/reset', function () {
    cache()->forget('origin-hits');

    return ['ok' => true];
});

// Demo only: a dynamic route that wrongly sends a public Cache-Control (no X-Inertia-Static-Cache).
// Without a CDN safety-net rule it would be cached; with the Cache Response Rule it must not be.
Route::get('/careless', fn () => response('<h1>careless '.now()->format('H:i:s.v').'</h1>')
    ->header('Cache-Control', 'public, max-age=300'))
    ->withoutMiddleware(config('inertia-static.strip_middleware')); // like a cookie-less API route
