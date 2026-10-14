<?php

namespace Fydellys\InertiaStatic\Tests\Fixtures\Http;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
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
            'appName' => 'Demo',
            'auth' => fn () => ['user' => $request->user()],
            'flash' => fn () => $request->hasSession() ? $request->session()->get('status') : null,
            'secret' => 'private-shared-value',
        ];
    }
}
