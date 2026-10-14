<?php

use Fydellys\InertiaStatic\Http\Middleware\RenderStatically;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;

it('removes the session pipeline from static routes', function () {
    $route = $this->router()->getRoutes()->getByName('home');
    $middleware = $this->router()->gatherRouteMiddleware($route);

    expect($middleware)
        ->not->toContain(StartSession::class)
        ->not->toContain(EncryptCookies::class)
        ->not->toContain(PreventRequestForgery::class)
        ->toContain(RenderStatically::class);
});

it('keeps the session pipeline on dynamic routes', function () {
    $route = $this->router()->getRoutes()->getByName('dashboard');

    expect($this->router()->gatherRouteMiddleware($route))->toContain(StartSession::class);
});

it('sends no Set-Cookie and starts no session on static routes', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty()
        ->and($response->headers->has('Set-Cookie'))->toBeFalse()
        ->and(app('request')->hasSession())->toBeFalse();
});

it('strips cookies set by application code', function () {
    $response = $this->get('/leak/set-cookie');

    expect($response->headers->getCookies())->toBeEmpty()
        ->and((string) $response->headers->get('Vary'))->not->toContain('Cookie');
});

it('only exposes whitelisted shared props', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'])
        ->get('/__inertia/v1/index.json');

    $props = $response->json('props');

    expect($props)->toHaveKeys(['appName', 'errors', 'title'])
        ->not->toHaveKey('auth')
        ->not->toHaveKey('flash')
        ->not->toHaveKey('secret')
        ->and($props['errors'])->toBe([]);
});

it('keeps every shared prop on dynamic routes', function () {
    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'])->get('/dashboard');

    expect($response->json('props'))->toHaveKeys(['auth', 'flash', 'secret', 'appName']);
});

it('ignores incoming cookies so no identity can be resolved', function () {
    $this->withUnencryptedCookies(['laravel_session' => 'abc', 'remember_web_x' => 'y'])
        ->get('/')
        ->assertOk()
        ->assertHeader('X-Inertia-Static', 'MISS')->assertTtl(600);
});
