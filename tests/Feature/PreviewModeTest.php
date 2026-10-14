<?php

use Fydellys\InertiaStatic\Facades\StaticCache;

it('skips the cache with a valid preview cookie', function () {
    $cookie = StaticCache::enablePreview();

    $response = $this->withUnencryptedCookies([$cookie->getName() => $cookie->getValue()])
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'v1'])
        ->get('/__inertia/live/v1/preview-check.json');

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('props.preview', true);

    $this->withUnencryptedCookies([$cookie->getName() => $cookie->getValue()])
        ->get('/blog')
        ->assertHeader('X-Inertia-Static-Reason', 'preview')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('ignores forged or expired preview cookies', function (string $value) {
    $this->withUnencryptedCookies(['inertia_static_preview' => $value])
        ->get('/blog')
        ->assertHeader('X-Inertia-Static', 'MISS');

    $this->withUnencryptedCookies(['inertia_static_preview' => $value])
        ->get('/__inertia/v1/preview-check.json')
        ->assertJsonPath('props.preview', false);
})->with([
    'forged' => ['9999999999.deadbeef'],
    'expired' => [fn () => '1.'.hash_hmac('sha256', 'inertia-static-preview|1', config('app.key'))],
    'garbage' => ['nope'],
]);

it('issues and clears the preview cookie', function () {
    $on = StaticCache::enablePreview(5);
    $off = StaticCache::disablePreview();

    expect($on->getName())->toBe('inertia_static_preview')
        ->and($on->isHttpOnly())->toBeFalse()
        ->and($on->getExpiresTime())->toBeGreaterThan(time() + 250)
        ->and($off->getExpiresTime())->toBeLessThan(time());
});

it('exposes the preview cookie name to the client', function () {
    $this->get('/__inertia/manifest.json')->assertJsonPath('previewCookie', 'inertia_static_preview');
});
