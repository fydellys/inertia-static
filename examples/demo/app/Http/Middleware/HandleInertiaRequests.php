<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Public: survives on static pages (inertia-static.public_shared_props).
            'appName' => config('app.name'),
            // Private: dropped on static pages.
            // As in the Laravel starter kits (read eagerly): on static routes it is null, then dropped.
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email')],
            'flash' => fn () => $request->hasSession() ? $request->session()->get('status') : null,
            'csrf' => fn () => $request->hasSession() ? $request->session()->token() : null,
        ];
    }
}
