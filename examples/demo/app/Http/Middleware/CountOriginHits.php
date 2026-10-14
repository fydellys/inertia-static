<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo only: counts every request that reaches the ORIGIN (i.e. was not served
 * by the CDN), split into static pages (X-Inertia-Static header) and dynamic.
 */
class CountOriginHits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('__origin/*', 'build/*', 'favicon.ico')) {
            return $response;
        }

        $type = $response->headers->has('X-Inertia-Static') ? 'static' : 'dynamic';

        Cache::lock('origin-hits', 5)->block(5, function () use ($request, $response, $type) {
            $stats = Cache::get('origin-hits', ['static' => 0, 'dynamic' => 0, 'log' => []]);
            $stats[$type]++;
            array_unshift($stats['log'], [
                'at' => now()->format('H:i:s.v'),
                'type' => $type,
                'method' => $request->method(),
                'uri' => $request->getRequestUri(),
                'status' => $response->getStatusCode(),
                'xInertia' => $request->headers->has('X-Inertia'),
            ]);
            $stats['log'] = array_slice($stats['log'], 0, 30);
            Cache::forever('origin-hits', $stats);
        });


        return $response;
    }
}
