<?php

namespace Fydellys\InertiaStatic\Exceptions;

use Fydellys\InertiaStatic\Enums\Capability;
use RuntimeException;

class UnsupportedOperation extends RuntimeException
{
    public static function for(string $driver, Capability $capability): self
    {
        return new self("[inertia-static] The [{$driver}] CDN driver does not support [{$capability->value}].");
    }
}
