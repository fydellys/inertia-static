<?php

namespace Fydellys\InertiaStatic\Security;

use Closure;
use Fydellys\InertiaStatic\Events\StaticRenderViolated;
use Fydellys\InertiaStatic\Exceptions\StaticRenderViolation;
use Fydellys\InertiaStatic\Support\StaticRequestContext;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Inertia\Support\Header;

/**
 * Leak detector: while a static page renders, the session, the auth layer,
 * the cookie jar and auth headers are replaced by traps that report any
 * access. Request cookies are already removed by ApplyStaticCache, so
 * $request->cookie() deterministically returns null (anonymous visitor).
 */
class RenderGuard
{
    public const SESSION = 'session';

    public const AUTH = 'auth';

    public const COOKIE = 'cookie';

    public const HEADER = 'header';

    protected const TRAPPED = ['auth', 'session', 'session.store', 'cookie'];

    protected ?Request $request = null;

    /** @var array<string, mixed> */
    protected array $originals = [];

    protected bool $installed = false;

    public function __construct(
        protected Application $app,
        protected Repository $config,
        protected StaticRequestContext $context,
    ) {}

    /**
     * Run the callback with every private-data accessor trapped.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function protect(Request $request, Closure $callback): mixed
    {
        $this->install($request);

        try {
            return $callback();
        } finally {
            $this->restore();
        }
    }

    public function install(Request $request): void
    {
        if ($this->installed) {
            return;
        }

        $this->request = $request;
        $this->installed = true;

        foreach (self::TRAPPED as $abstract) {
            $this->originals[$abstract] = $this->app->bound($abstract) && $this->app->resolved($abstract)
                ? $this->app->make($abstract)
                : null;
        }

        $this->originals['user_resolver'] = $request->getUserResolver();
        $this->originals['headers'] = $request->headers;

        $this->app->instance('auth', new AuthTrap($this));
        $session = new SessionTrap($this);

        $this->app->instance('session', new SessionManagerTrap($this->app, $session));
        $this->app->instance('session.store', $session);
        $this->app->instance('cookie', new CookieJarTrap($this));

        $request->setUserResolver(fn () => $this->violation(self::AUTH, 'request()->user()'));
        $request->headers = new HeaderTrap($request->headers->all(), $this);

        $this->clearFacades();
    }

    public function restore(): void
    {
        if (! $this->installed || $this->request === null) {
            return;
        }

        foreach (self::TRAPPED as $abstract) {
            if ($this->originals[$abstract] !== null) {
                $this->app->instance($abstract, $this->originals[$abstract]);
            } else {
                $this->app->forgetInstance($abstract);
            }
        }

        $this->request->setUserResolver($this->originals['user_resolver']);
        $this->request->headers = $this->originals['headers'];

        $this->clearFacades();

        $this->originals = [];
        $this->request = null;
        $this->installed = false;
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    /**
     * Report an access to private data. Throws in "throw" mode; otherwise
     * logs it and marks the response as not cacheable.
     */
    public function violation(string $type, string $detail): mixed
    {
        // Laravel's exception reporting (a handled exception reported with rescue() or report())
        // reads Auth::id() for the log context: it reaches the log, never the page.
        if ($this->isExceptionReporting()) {
            return null;
        }

        $violation = new StaticRenderViolation($type, $detail, $this->request?->getRequestUri());

        $this->context->recordViolation($violation);
        $this->context->bypass("leak:{$type}");

        $this->app->make(Dispatcher::class)->dispatch(new StaticRenderViolated($violation));

        if ($this->shouldThrow()) {
            throw $violation;
        }

        // "auto": the route stops being static (it becomes a regular dynamic route). Persisted by
        // RenderStatically only when the render succeeds: Laravel's exception reporting reads
        // Auth::id(), and a failed request must not turn a route dynamic for good.
        // Not for a partial reload: it resolves props (optional, deferred, scroll) the cached
        // render never resolves, and it is never cached, so the cached page is still safe.
        if ($this->mode() === 'auto' && ! $this->isPartialReload() && ($route = $this->request?->route()) instanceof Route) {
            $this->context->pendingDowngrade ??= ['route' => $route, 'reason' => "{$type}: {$detail}"];
        }

        $channel = $this->config->get('inertia-static.leak_detector.log_channel');

        ($channel ? Log::channel($channel) : Log::getFacadeRoot())
            ->warning($violation->getMessage(), $violation->context());

        return null;
    }

    /**
     * Whether the access comes from Laravel's exception handler building its report context.
     */
    protected function isExceptionReporting(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            if (isset($frame['class']) && is_a($frame['class'], ExceptionHandler::class, true)
                && in_array($frame['function'], ['report', 'reportThrowable', 'context', 'exceptionContext', 'buildExceptionContext'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function isPartialReload(): bool
    {
        $headers = $this->request?->headers;

        return $headers !== null && ($headers->has(Header::PARTIAL_COMPONENT)
            || $headers->has(Header::PARTIAL_ONLY)
            || $headers->has(Header::PARTIAL_EXCEPT)
            || $headers->has(Header::RESET));
    }

    public function shouldThrow(): bool
    {
        return $this->mode() === 'throw';
    }

    /**
     * "throw" (local / testing by default), "auto" (elsewhere: log + downgrade the route) or "log".
     */
    public function mode(): string
    {
        $mode = $this->config->get('inertia-static.leak_detector.mode');

        if (is_string($mode) && $mode !== '') {
            return $mode;
        }

        return $this->app->environment((array) $this->config->get('inertia-static.leak_detector.throw_in', ['local', 'testing']))
            ? 'throw'
            : 'auto';
    }

    protected function clearFacades(): void
    {
        Facade::clearResolvedInstance('auth');
        Facade::clearResolvedInstance('session');
        Facade::clearResolvedInstance('session.store');
        Facade::clearResolvedInstance('cookie');
    }
}
