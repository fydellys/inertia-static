<?php

use Fydellys\InertiaStatic\Enums\RenderMode;
use Fydellys\InertiaStatic\Exceptions\InvalidStaticRoute;
use Fydellys\InertiaStatic\Support\RouteOptions;
use Fydellys\InertiaStatic\Tests\Fixtures\PostPaths;
use Illuminate\Support\Facades\Route;

it('stores the options in the route action', function () {
    $options = RouteOptions::fromRoute($this->router()->getRoutes()->getByName('blog.show'));

    expect($options->mode)->toBe(RenderMode::Isr)
        ->and($options->paths)->toBe(PostPaths::class)
        ->and($options->tags)->toBe(['posts']);
});

it('applies the #[StaticPage] attribute', function () {
    $options = RouteOptions::fromRoute($this->router()->getRoutes()->getByName('attribute'));

    expect($options->mode)->toBe(RenderMode::Isr)->and($options->revalidate)->toBe(120);

    $this->get('/attribute')->assertOk()->assertHeader('X-Inertia-Static', 'MISS')->assertTtl(120);
});

it('requires a paths provider for parameterized static routes', function () {
    Route::get('/docs/{page}', fn () => 'x')->static();
})->throws(InvalidStaticRoute::class, 'paths provider');

it('rejects non-GET static routes', function () {
    Route::post('/form', fn () => 'x')->isr();
})->throws(InvalidStaticRoute::class);

it('rejects static routes that also accept other verbs (their CSRF middleware is removed)', function () {
    Route::match(['GET', 'POST'], '/form', fn () => 'x')->isr();
})->throws(InvalidStaticRoute::class);

it('marks routes as dynamic explicitly', function () {
    $route = Route::get('/explicit', fn () => 'x')->dynamic();

    expect(RouteOptions::fromRoute($route)->mode)->toBe(RenderMode::Dynamic);
});

it('lists static routes', function () {
    $this->artisan('inertia-static:routes')
        ->expectsOutputToContain('/blog/{slug}')
        ->expectsOutputToContain('isr')
        ->assertSuccessful();
});

it('writes the client manifest', function () {
    $path = sys_get_temp_dir().'/inertia-static-manifest-'.uniqid().'.json';

    $this->artisan('inertia-static:manifest', ['--path' => $path])->assertSuccessful();

    expect(json_decode(file_get_contents($path), true)['routes'])->not->toBeEmpty();

    unlink($path);
});
