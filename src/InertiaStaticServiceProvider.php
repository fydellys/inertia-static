<?php

namespace Fydellys\InertiaStatic;

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Fydellys\InertiaStatic\Enums\RenderMode;
use Fydellys\InertiaStatic\Http\Middleware\ApplyStaticCache;
use Fydellys\InertiaStatic\Http\Middleware\RenderStatically;
use Fydellys\InertiaStatic\Http\Middleware\RewriteInertiaRedirects;
use Fydellys\InertiaStatic\Jobs\PurgeStaticCache;
use Fydellys\InertiaStatic\Registry\RegistryManager;
use Fydellys\InertiaStatic\Routing\AttributeRouteResolver;
use Fydellys\InertiaStatic\Routing\StaticRouteConfigurator;
use Fydellys\InertiaStatic\Security\RenderGuard;
use Fydellys\InertiaStatic\Support\StaticRequestContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Response as InertiaResponse;
use Inertia\Ssr\SsrState;
use LogicException;

class InertiaStaticServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inertia-static.php', 'inertia-static');

        $this->ensureCompatibleInertia();

        // Request-lifecycle state: "scoped" is reset per request / job (Octane safe).
        $this->app->scoped(StaticRequestContext::class);
        $this->app->scoped(RenderGuard::class);
        $this->app->scoped(CdnManager::class);
        $this->app->scoped(RegistryManager::class);
        $this->app->scoped(StaticCache::class);

        $this->app->bind(TagRegistry::class, fn ($app) => $app->make(RegistryManager::class)->driver());
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerRouteMacros();
        $this->registerResponseMacros();
        $this->registerMiddleware();
        $this->registerRateLimiter();

        $this->loadRoutesFrom(__DIR__.'/../routes/inertia-static.php');

        $this->app->booted(fn () => $this->app->make(AttributeRouteResolver::class)->resolve());
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/inertia-static.php' => config_path('inertia-static.php'),
        ], 'inertia-static-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations/create_inertia_static_tags_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_inertia_static_tags_table.php'),
        ], 'inertia-static-migrations');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\RoutesCommand::class,
                Console\ManifestCommand::class,
                Console\PurgeCommand::class,
                Console\WarmCommand::class,
                Console\DoctorCommand::class,
            ]);
        }
    }

    /**
     * Route::get(...)->isr(revalidate: 60), ->static(paths: PostPaths::class), ->dynamic()
     */
    protected function registerRouteMacros(): void
    {
        foreach (RenderMode::cases() as $mode) {
            Route::macro($mode->value, function (
                ?int $revalidate = null,
                ?string $paths = null,
                array $tags = [],
                array $query = [],
                ?int $staleWhileRevalidate = null,
                ?int $staleIfError = null,
            ) use ($mode) {
                /** @var Route $this */
                return app(StaticRouteConfigurator::class)->apply($this, $mode, [
                    'revalidate' => $revalidate,
                    'paths' => $paths,
                    'tags' => $tags,
                    'query' => $query,
                    'stale_while_revalidate' => $staleWhileRevalidate,
                    'stale_if_error' => $staleIfError,
                ]);
            });
        }
    }

    /**
     * Inertia::render(...)->revalidate(60)->staleWhileRevalidate(600)->tags([...])
     */
    protected function registerResponseMacros(): void
    {
        $override = fn (string $key) => function (?int $seconds) use ($key) {
            app(StaticRequestContext::class)->override($key, $seconds);

            return $this;
        };

        InertiaResponse::macro('revalidate', $override('revalidate'));
        InertiaResponse::macro('staleWhileRevalidate', $override('stale_while_revalidate'));
        InertiaResponse::macro('staleIfError', $override('stale_if_error'));
        InertiaResponse::macro('tags', function (array|string $tags) {
            app(StaticRequestContext::class)->addTags($tags);

            return $this;
        });
    }

    protected function registerMiddleware(): void
    {
        $this->app->make(Router::class)->aliasMiddleware(RenderStatically::ALIAS, RenderStatically::class);

        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof Kernel && ! $kernel->hasMiddleware(ApplyStaticCache::class)) {
            $kernel->pushMiddleware(ApplyStaticCache::class);
        }

        if ($kernel instanceof Kernel && ! $kernel->hasMiddleware(RewriteInertiaRedirects::class)) {
            $kernel->pushMiddleware(RewriteInertiaRedirects::class);
        }
    }

    protected function registerRateLimiter(): void
    {
        RateLimiter::for(PurgeStaticCache::RATE_LIMITER, fn () => Limit::perMinute(
            max(1, (int) config('inertia-static.purge.rate_limit_per_minute', 60)),
        ));
    }

    protected function ensureCompatibleInertia(): void
    {
        if (! class_exists(SsrState::class)) {
            throw new LogicException('[inertia-static] Requires inertiajs/inertia-laravel ^3.0. Run: composer require inertiajs/inertia-laravel:^3.0');
        }
    }
}
