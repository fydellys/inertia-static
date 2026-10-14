<?php

namespace Fydellys\InertiaStatic\Security;

use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Inertia\Support\SessionKey;

/**
 * Stands in for the session store while a static page renders. It is a real
 * Store (so type-hinted framework services keep working) whose accessors
 * report the access and behave like an empty session.
 *
 * Inertia itself reads a few per-visitor flags from the session while it
 * builds every response (clear history, preserve fragment). On a shared page
 * those flags must take their default value, so reads of exactly those keys
 * are not reported.
 */
class SessionTrap extends Store
{
    public const INERTIA_KEYS = [
        SessionKey::CLEAR_HISTORY,
        SessionKey::PRESERVE_FRAGMENT,
    ];

    public function __construct(protected RenderGuard $guard)
    {
        parent::__construct('inertia-static-trap', new NullSessionHandler);
    }

    public function get($key, $default = null)
    {
        return $this->read('get', $key, $default);
    }

    public function pull($key, $default = null)
    {
        return $this->read('pull', $key, $default);
    }

    public function has($key)
    {
        $this->report('has', $key);

        return false;
    }

    public function exists($key)
    {
        $this->report('exists', $key);

        return false;
    }

    public function all()
    {
        $this->report('all');

        return [];
    }

    public function only(array $keys)
    {
        $this->report('only');

        return [];
    }

    public function except(array $keys)
    {
        $this->report('except');

        return [];
    }

    public function put($key, $value = null)
    {
        $this->report('put', $key);
    }

    public function push($key, $value)
    {
        $this->report('push', $key);
    }

    public function flash(\BackedEnum|\UnitEnum|string $key, $value = true)
    {
        $this->report('flash', is_string($key) ? $key : null);
    }

    public function now($key, $value)
    {
        $this->report('now', $key);
    }

    public function reflash()
    {
        $this->report('reflash');
    }

    public function forget($keys)
    {
        $this->report('forget');
    }

    public function token()
    {
        $this->report('token');

        return '';
    }

    public function regenerateToken()
    {
        $this->report('regenerateToken');
    }

    public function getId()
    {
        $this->report('getId');

        return '';
    }

    public function hasOldInput($key = null)
    {
        $this->report('hasOldInput', $key);

        return false;
    }

    public function getOldInput($key = null, $default = null)
    {
        $this->report('getOldInput', $key);

        return value($default);
    }

    public function previousUrl()
    {
        $this->report('previousUrl');

        return null;
    }

    public function setPreviousUrl($url)
    {
        $this->report('setPreviousUrl');
    }

    public function start()
    {
        $this->report('start');

        return false;
    }

    public function save()
    {
        $this->report('save');
    }

    protected function read(string $method, mixed $key, mixed $default): mixed
    {
        if (! (is_string($key) && in_array($key, self::INERTIA_KEYS, true))) {
            $this->report($method, $key);
        }

        return value($default);
    }

    protected function report(string $method, mixed $key = null): void
    {
        $argument = is_string($key) ? "'{$key}'" : '';

        $this->guard->violation(RenderGuard::SESSION, "session()->{$method}({$argument})");
    }
}
