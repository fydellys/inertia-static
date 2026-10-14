<?php

namespace Fydellys\InertiaStatic\Security;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

class GuardTrap implements Guard
{
    public function __construct(protected RenderGuard $guard, protected ?string $name = null) {}

    public function check(): bool
    {
        $this->report('check');

        return false;
    }

    public function guest(): bool
    {
        $this->report('guest');

        return true;
    }

    public function user(): ?Authenticatable
    {
        $this->report('user');

        return null;
    }

    public function id(): int|string|null
    {
        $this->report('id');

        return null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        $this->report('validate');

        return false;
    }

    public function hasUser(): bool
    {
        $this->report('hasUser');

        return false;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->report('setUser');

        return $this;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $this->report($method);

        return null;
    }

    protected function report(string $method): void
    {
        $guard = $this->name ? "guard('{$this->name}')->" : '';

        $this->guard->violation(RenderGuard::AUTH, "auth()->{$guard}{$method}()");
    }
}
