<?php

namespace Fydellys\InertiaStatic\Tests;

use Fydellys\InertiaStatic\InertiaStaticServiceProvider;
use Fydellys\InertiaStatic\Tests\Fixtures\FakeSsrGateway;
use Fydellys\InertiaStatic\Tests\Fixtures\Http\HandleInertiaRequests;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Ssr\Gateway;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Assert;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // The edge TTL lives in the CDN header, not in X-Inertia-Static.
        TestResponse::macro('assertTtl', function (int $seconds) {
            /** @var TestResponse $this */
            $edge = $this->headers->get('CDN-Cache-Control') ?? $this->headers->get('Cloudflare-CDN-Cache-Control') ?? '';
            Assert::assertMatchesRegularExpression("/(^|, )max-age={$seconds}(,|$)/", $edge, "Edge TTL is not {$seconds}s: [{$edge}]");

            return $this;
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            InertiaStaticServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config) {
            $config->set('app.url', 'https://example.com');
            $config->set('inertia-static.base_url', 'https://example.com');
            $config->set('inertia-static.inertia_middleware', HandleInertiaRequests::class);
            $config->set('inertia-static.public_shared_props', ['appName']);
            $config->set('inertia.ssr.enabled', true);
            $config->set('inertia.ssr.ensure_bundle_exists', false);
            $config->set('inertia.pages.ensure_pages_exist', false);
            $config->set('inertia.testing.ensure_pages_exist', false);
            $config->set('view.paths', [__DIR__.'/Fixtures/views']);
            $config->set('session.driver', 'array');
        });

        $app->bind(Gateway::class, FakeSsrGateway::class);
    }

    protected function defineRoutes($router): void
    {
        $router->pushMiddlewareToGroup('web', HandleInertiaRequests::class);

        require __DIR__.'/Fixtures/routes.php';
    }

    protected function router(): Router
    {
        return $this->app->make(Router::class);
    }
}
