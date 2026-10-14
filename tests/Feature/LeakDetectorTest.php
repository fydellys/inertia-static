<?php

use Fydellys\InertiaStatic\Events\StaticRenderViolated;
use Fydellys\InertiaStatic\Exceptions\StaticRenderViolation;
use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Auth\User;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

dataset('leaks', [
    'session' => ['/leak/session', 'session'],
    'auth()->check()' => ['/leak/auth', 'auth'],
    'Auth::id()' => ['/leak/auth-facade', 'auth'],
    'request()->user()' => ['/leak/request-user', 'auth'],
    'cookie' => ['/leak/cookie', 'cookie'],
    'auth header' => ['/leak/header', 'header'],
]);

it('throws in testing for every kind of private access', function (string $uri, string $type) {
    $this->withoutExceptionHandling();

    try {
        $this->get($uri);
    } catch (StaticRenderViolation $e) {
        expect($e->type)->toBe($type);

        return;
    }

    $this->fail("No violation thrown for {$uri}");
})->with('leaks');

it('logs and refuses to cache in log mode', function (string $uri, string $type) {
    config(['inertia-static.leak_detector.mode' => 'log']);
    Event::fake([StaticRenderViolated::class]);
    Log::spy();

    $response = $this->get($uri);

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Inertia-Static', "BYPASS; reason=leak:{$type}");

    Event::assertDispatched(StaticRenderViolated::class, fn ($event) => $event->violation->type === $type);
    Log::shouldHaveReceived('warning')->atLeast()->once();
})->with('leaks');

it('restores the real auth and session services after rendering', function () {
    $this->get('/');

    expect(app('auth'))->toBeInstanceOf(AuthManager::class)
        ->and(app('session'))->toBeInstanceOf(SessionManager::class);
});

it('restores the services even when the render throws', function () {
    $this->withoutExceptionHandling();

    try {
        $this->get('/leak/auth');
    } catch (StaticRenderViolation) {
    }

    expect(app('auth'))->toBeInstanceOf(AuthManager::class);
});

it('does not flag a logged-in visitor of a static page and leaks nothing', function () {
    $user = new User;
    $user->forceFill(['id' => 42, 'name' => 'Alice']);

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'])
        ->get('/__inertia/v1/index.json')
        ->assertOk()
        ->assertJsonMissingPath('props.auth')
        ->assertDontSee('Alice');
});

it('removes request cookies so reading them yields the anonymous value', function () {
    $this->withUnencryptedCookies(['theme' => 'dark'])
        ->get('/__inertia/v1/read-cookie.json')
        ->assertOk()
        ->assertJsonPath('props.theme', null)
        ->assertHeader('X-Inertia-Static', 'MISS; ttl=3600');
});
