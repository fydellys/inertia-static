<?php

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, every route behaves as "dynamic": no cache headers are
    | emitted and the session pipeline is left untouched.
    |
    */

    'enabled' => env('INERTIA_STATIC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Absolute URLs (purge, warmup, sitemap, canonical) are ALWAYS built from
    | this value and never from the incoming Host / X-Forwarded-Host headers,
    | protecting the cache against unkeyed-header poisoning.
    |
    */

    'base_url' => env('INERTIA_STATIC_BASE_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Inertia Middleware
    |--------------------------------------------------------------------------
    |
    | The application's HandleInertiaRequests middleware. It is used to resolve
    | the current asset version outside of an HTTP request (queues, commands).
    |
    */

    'inertia_middleware' => 'App\\Http\\Middleware\\HandleInertiaRequests',

    /*
    |--------------------------------------------------------------------------
    | JSON Endpoint
    |--------------------------------------------------------------------------
    |
    | Inertia answers HTML and JSON on the same URL (varying on X-Inertia).
    | Most CDNs ignore Vary for arbitrary headers, so the page JSON of static
    | routes is served from its own URL: /{prefix}/{version}/{path}.json
    |
    */

    'json' => [
        'prefix' => '__inertia',
        'index' => 'index',
        'live_middleware' => [
            Illuminate\Cookie\Middleware\EncryptCookies::class,
            Illuminate\Session\Middleware\StartSession::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Stripping
    |--------------------------------------------------------------------------
    |
    | Middleware removed from the pipeline of "isr" and "static" routes.
    | Classes that do not exist in the installed Laravel version are ignored.
    |
    */

    'strip_middleware' => [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        AuthenticateSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class, // Laravel 13+
        ValidateCsrfToken::class,
        VerifyCsrfToken::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Shared Props
    |--------------------------------------------------------------------------
    |
    | Only these top-level shared props (from HandleInertiaRequests::share)
    | survive on static routes. "auth", "flash", "errors" and anything not
    | listed here are dropped. "errors" is always sent as an empty object.
    |
    */

    'public_shared_props' => [
        'appName',
    ],

    /*
    |--------------------------------------------------------------------------
    | Leak Detector
    |--------------------------------------------------------------------------
    |
    | Detects access to the session, the authenticated user, cookies or auth
    | headers while a static page renders.
    |
    | "throw": throws StaticRenderViolation (default in local / testing).
    | "log":   logs the violation and serves the response with no-store.
    |
    */

    'leak_detector' => [
        'mode' => env('INERTIA_STATIC_LEAK_DETECTOR'),
        'throw_in' => ['local', 'testing'],
        'log_channel' => env('INERTIA_STATIC_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSR
    |--------------------------------------------------------------------------
    |
    | When "required" is true, an HTML response rendered without SSR (the
    | gateway is disabled or failed) is NEVER cached: it is sent as no-store.
    |
    */

    'ssr' => [
        'required' => env('INERTIA_STATIC_SSR_REQUIRED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred Props
    |--------------------------------------------------------------------------
    |
    | Deferred props on a static route are fetched from the origin through a
    | partial reload. "throw" fails loudly (dev), "allow" keeps the page
    | cacheable and lets the client fetch them dynamically.
    |
    */

    'deferred_props' => env('INERTIA_STATIC_DEFERRED_PROPS', 'throw'),

    /*
    |--------------------------------------------------------------------------
    | Cache Policy Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'revalidate' => 3600,
        'stale_while_revalidate' => 86400,
        'stale_if_error' => 604800,
        'browser_cache_control' => 'public, max-age=0, must-revalidate',
        'not_found_ttl' => 60,
        'cache_redirects' => false,
        'redirect_ttl' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Normalization
    |--------------------------------------------------------------------------
    |
    | Query parameters not whitelisted on the route are removed with a 301
    | to the canonical URL. Tracking parameters are always removed.
    |
    | trailing_slash: "never", "always" or "ignore".
    |
    */

    'normalization' => [
        'enabled' => true,
        'trailing_slash' => 'never',
        'strip_query' => ['utm_*', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', 'ref'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Header
    |--------------------------------------------------------------------------
    */

    'debug_header' => env('INERTIA_STATIC_DEBUG_HEADER', 'X-Inertia-Static'),

    /*
    |--------------------------------------------------------------------------
    | CDN Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "null", "cloudflare", "fastly", "bunny", "varnish", "nginx",
    | "multi". Custom drivers can be added with StaticCache::extend().
    |
    */

    'cdn' => env('INERTIA_STATIC_CDN', 'null'),

    'drivers' => [

        'null' => [
            'driver' => 'null',
        ],

        'cloudflare' => [
            'driver' => 'cloudflare',
            'zone_id' => env('CLOUDFLARE_ZONE_ID'),
            'api_token' => env('CLOUDFLARE_API_TOKEN'),
            // 100 items per purge request on every plan (Enterprise: 500 URLs).
            // Rate limits are per account (Free: 5 purge requests / minute).
            'batch_size' => (int) env('CLOUDFLARE_PURGE_BATCH_SIZE', 100),
        ],

        'fastly' => [
            'driver' => 'fastly',
            'service_id' => env('FASTLY_SERVICE_ID'),
            'api_token' => env('FASTLY_API_TOKEN'),
            'soft_purge' => true,
        ],

        'bunny' => [
            'driver' => 'bunny',
            'pull_zone_id' => env('BUNNY_PULL_ZONE_ID'),
            'api_key' => env('BUNNY_API_KEY'),
            // Bunny stores one CDN-Tag per response; keep false to purge tags via the registry.
            'native_tags' => false,
        ],

        'varnish' => [
            'driver' => 'varnish',
            'hosts' => array_filter(explode(',', (string) env('VARNISH_HOSTS', 'http://127.0.0.1:6081'))),
            'tag_header' => 'xkey',
        ],

        'nginx' => [
            'driver' => 'nginx',
            'hosts' => array_filter(explode(',', (string) env('NGINX_CACHE_HOSTS', 'http://127.0.0.1'))),
            'purge_path' => '/purge',
        ],

        'multi' => [
            'driver' => 'multi',
            'drivers' => ['varnish', 'cloudflare'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Tag Registry
    |--------------------------------------------------------------------------
    |
    | Maps tag => URLs so CDNs without tag purging can still purge by tag
    | (converted to URL purges of both the HTML and the JSON variants).
    |
    | Supported: null (disabled), "database", "cache".
    |
    */

    'registry' => [
        'driver' => env('INERTIA_STATIC_REGISTRY'),
        'table' => 'inertia_static_tags',
        'connection' => null,
        'store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Purging
    |--------------------------------------------------------------------------
    */

    'purge' => [
        'queue' => env('INERTIA_STATIC_PURGE_QUEUE'),
        'connection' => env('INERTIA_STATIC_PURGE_CONNECTION'),
        'debounce' => 5,
        'tries' => 5,
        'backoff' => [5, 30, 120],
        'rate_limit_per_minute' => 60,
        'warm_after_purge' => false,
        'warm_url' => env('INERTIA_STATIC_WARM_URL'),
    ],

];
