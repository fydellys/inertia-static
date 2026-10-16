<?php

use Fydellys\InertiaStatic\Tests\Fixtures\Http\HandleInertiaRequests;
use Fydellys\InertiaStatic\Tests\Fixtures\Http\StarterKitHandleInertiaRequests;
use Illuminate\Foundation\Auth\User;

beforeEach(function () {
    $group = array_map(
        fn (string $middleware) => $middleware === HandleInertiaRequests::class ? StarterKitHandleInertiaRequests::class : $middleware,
        $this->router()->getMiddlewareGroups()['web'],
    );
    $this->router()->middlewareGroup('web', $group);
});

dataset('modes', ['throw', 'log', 'auto']);

it('serves a static page with the starter kit share(), signed in or not', function (string $mode) {
    config(['inertia-static.leak_detector.mode' => $mode, 'inertia-static.public_shared_props' => ['name', 'sidebarOpen']]);
    $alice = (new User)->forceFill(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);

    foreach ([null, $alice] as $user) {
        $response = ($user ? $this->actingAs($user) : $this)
            ->withCookie('sidebar_state', 'false')
            ->get('/blog')
            ->assertOk()
            ->assertHeader('X-Inertia-Static', 'MISS')
            ->assertHeader('X-Inertia-Static-Cache', 'cacheable');

        $response->assertViewHas('page', fn (array $page) => ! array_key_exists('auth', $page['props'])
            && $page['props']['name'] === 'Demo'
            && $page['props']['sidebarOpen'] === true);
        expect($response->getContent())->not->toContain('Alice');
    }
})->with('modes');

it('keeps the user on dynamic pages with the starter kit share()', function () {
    $alice = (new User)->forceFill(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);

    $this->actingAs($alice)->get('/dashboard')->assertOk()
        ->assertViewHas('page', fn (array $page) => $page['props']['auth']['user']['name'] === 'Alice');
});
