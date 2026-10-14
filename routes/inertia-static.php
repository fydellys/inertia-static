<?php

use Fydellys\InertiaStatic\Http\Controllers\ManifestController;
use Fydellys\InertiaStatic\Http\Controllers\PageJsonController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('inertia-static.json.prefix', '__inertia'), '/');

Route::get("{$prefix}/manifest.json", ManifestController::class)
    ->name('inertia-static.manifest');

// Never cached: reloads, polling, partial reloads and deferred props of static pages.
// Registered before the cacheable route, whose {version} would also match "live".
Route::get("{$prefix}/live/{version}/{path}", [PageJsonController::class, 'live'])
    ->middleware((array) config('inertia-static.json.live_middleware', []))
    ->where('path', '.+\.json')
    ->name('inertia-static.live');

Route::get("{$prefix}/{version}/{path}", PageJsonController::class)
    ->where('path', '.+\.json')
    ->name('inertia-static.page');
