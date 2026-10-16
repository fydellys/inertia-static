<?php

namespace Fydellys\InertiaStatic\Tests\Fixtures\Http;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * share() as in the Laravel starter kits: the user read eagerly, a cookie read.
 */
class StarterKitHandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return 'v1';
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => 'Demo',
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
