<?php

namespace Fydellys\InertiaStatic\Console;

use Fydellys\InertiaStatic\Cdn\CdnManager;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Http\Middleware\ApplyStaticCache;
use Fydellys\InertiaStatic\Routing\StaticRouteConfigurator;
use Fydellys\InertiaStatic\Support\Downgrades;
use Fydellys\InertiaStatic\Support\Manifest;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HasHealthCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'inertia-static:doctor')]
class DoctorCommand extends Command
{
    protected $signature = 'inertia-static:doctor';

    protected $description = 'Validate the inertia-static configuration, SSR, driver and routes';

    protected int $errors = 0;

    public function handle(Kernel $kernel, Router $router, Manifest $manifest, CdnManager $cdn, StaticRouteConfigurator $configurator): int
    {
        $this->components->info('inertia-static doctor');

        $this->check('Package enabled', (bool) config('inertia-static.enabled'), 'INERTIA_STATIC_ENABLED is false.', warn: true);
        $this->check('Global middleware registered', method_exists($kernel, 'hasMiddleware') && $kernel->hasMiddleware(ApplyStaticCache::class), 'ApplyStaticCache is not in the global middleware stack.');
        $this->check('Base URL is absolute', (bool) filter_var(config('inertia-static.base_url'), FILTER_VALIDATE_URL), 'Set INERTIA_STATIC_BASE_URL / APP_URL.');
        $this->check('Inertia SSR enabled', (bool) config('inertia.ssr.enabled', false), 'Static pages will not be cached without SSR (inertia-static.ssr.required).', warn: ! config('inertia-static.ssr.required', true));
        $this->checkSsrHealth();

        try {
            $driver = $cdn->driver();
            $this->check('CDN driver ['.$cdn->getDefaultDriver().']', true);

            if (! $driver->canPurge()) {
                $this->check('Purge', false, 'Headers only mode: no API credentials, pages expire by TTL only (configure the driver credentials to purge).', warn: true);
            } elseif (! $driver->supports(Capability::PurgeTags) && ! config('inertia-static.registry.driver')) {
                $this->check('Tag purge', false, 'The driver has no tag purge: enable a tag registry (inertia-static.registry.driver).', warn: true);
            }
        } catch (Throwable $e) {
            $this->check('CDN driver ['.$cdn->getDefaultDriver().']', false, $e->getMessage());
        }

        foreach (app(Downgrades::class)->all() as $key => $downgrade) {
            $this->check("Route {$key}", false, "Turned dynamic by the leak detector ({$downgrade['reason']}). Fix the page, then run inertia-static:routes --reset-downgrades.", warn: true);
        }

        $stripped = $configurator->strippedMiddleware();

        foreach ($manifest->routes() as $entry) {
            $route = $entry['route'];

            if (! $entry['options']->mode->isCacheable()) {
                continue;
            }

            $middleware = $router->gatherRouteMiddleware($route);
            $leaking = array_intersect($stripped, $middleware);
            $auth = array_filter($middleware, fn ($m) => is_string($m) && (str_starts_with($m, 'auth') || str_contains($m, 'Authenticate')));

            $this->check(
                "Route /{$route->uri()} ({$entry['options']->mode->value})",
                $leaking === [] && $auth === [],
                $leaking !== []
                    ? 'Session middleware still active: '.implode(', ', $leaking)
                    : 'Authenticated routes cannot be static: '.implode(', ', $auth),
            );
        }

        $this->newLine();

        return $this->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function checkSsrHealth(): void
    {
        if (! config('inertia.ssr.enabled', false)) {
            return;
        }

        $gateway = app(Gateway::class);

        if ($gateway instanceof HasHealthCheck) {
            $this->check('SSR server reachable', $gateway->isHealthy(), 'The SSR server is not responding: HTML responses will be sent with no-store.');
        }
    }

    protected function check(string $label, bool $ok, string $hint = '', bool $warn = false): void
    {
        if ($ok) {
            $this->components->twoColumnDetail($label, '<fg=green;options=bold>OK</>');

            return;
        }

        $this->components->twoColumnDetail($label, $warn ? '<fg=yellow;options=bold>WARN</>' : '<fg=red;options=bold>FAIL</>');
        $this->components->bulletList([$hint]);

        if (! $warn) {
            $this->errors++;
        }
    }
}
