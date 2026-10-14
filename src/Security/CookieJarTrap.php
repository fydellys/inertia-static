<?php

namespace Fydellys\InertiaStatic\Security;

/**
 * Stands in for the cookie jar while a static page renders: creating or
 * queueing a cookie on a shared page is always a bug.
 */
class CookieJarTrap
{
    public function __construct(protected RenderGuard $guard) {}

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $name = is_string($arguments[0] ?? null) ? "'{$arguments[0]}'" : '';

        return $this->guard->violation(RenderGuard::COOKIE, "cookie()->{$method}({$name})");
    }
}
