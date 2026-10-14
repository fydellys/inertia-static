<?php

namespace Fydellys\InertiaStatic\Http\Controllers;

use Fydellys\InertiaStatic\Support\Manifest;
use Illuminate\Http\JsonResponse;

/**
 * GET /__inertia/manifest.json: the static route patterns used by the
 * client plugin to decide which visits are rewritten to the JSON endpoint.
 */
class ManifestController
{
    public function __invoke(Manifest $manifest): JsonResponse
    {
        return response()
            ->json($manifest->toArray())
            ->header('Cache-Control', 'public, max-age=60, must-revalidate');
    }
}
