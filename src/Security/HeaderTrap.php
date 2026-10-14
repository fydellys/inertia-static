<?php

namespace Fydellys\InertiaStatic\Security;

use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * Header bag that reports reads of authentication headers.
 */
class HeaderTrap extends HeaderBag
{
    public const SENSITIVE = [
        'authorization',
        'cookie',
        'php-auth-user',
        'php-auth-pw',
        'php-auth-digest',
        'x-xsrf-token',
        'x-csrf-token',
    ];

    /**
     * @param  array<string, array<int, string|null>|string|null>  $headers
     */
    public function __construct(array $headers, protected RenderGuard $guard)
    {
        parent::__construct($headers);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if ($this->isSensitive($key)) {
            $this->guard->violation(RenderGuard::HEADER, "header('{$key}')");

            return $default;
        }

        return parent::get($key, $default);
    }

    public function has(string $key): bool
    {
        if ($this->isSensitive($key)) {
            $this->guard->violation(RenderGuard::HEADER, "hasHeader('{$key}')");

            return false;
        }

        return parent::has($key);
    }

    public function all(?string $key = null): array
    {
        if ($key !== null && $this->isSensitive($key)) {
            $this->guard->violation(RenderGuard::HEADER, "header('{$key}')");

            return [];
        }

        return parent::all($key);
    }

    protected function isSensitive(string $key): bool
    {
        return in_array(strtr(strtolower($key), '_', '-'), self::SENSITIVE, true);
    }
}
