<?php

namespace Fydellys\InertiaStatic\Security;

use Illuminate\Contracts\Auth\Factory;

/**
 * Stands in for the AuthManager while a static page renders.
 */
class AuthTrap implements Factory
{
    public function __construct(protected RenderGuard $guard) {}

    public function guard($name = null): GuardTrap
    {
        return new GuardTrap($this->guard, $this->name($name));
    }

    public function shouldUse($name): void
    {
        $this->guard->violation(RenderGuard::AUTH, "auth()->shouldUse('{$this->name($name)}')");
    }

    public function userResolver(): \Closure
    {
        return fn () => $this->guard->violation(RenderGuard::AUTH, 'request()->user()');
    }

    protected function name(mixed $name): ?string
    {
        return match (true) {
            $name instanceof \BackedEnum => (string) $name->value,
            $name instanceof \UnitEnum => $name->name,
            default => $name === null ? null : (string) $name,
        };
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->guard()->{$method}(...$arguments);
    }
}
