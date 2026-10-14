<?php

namespace Fydellys\InertiaStatic\Exceptions;

use InvalidArgumentException;

class MissingCredentials extends InvalidArgumentException
{
    /**
     * @param  array<int, string>  $keys
     */
    public static function for(string $driver, array $keys): self
    {
        return new self(sprintf(
            '[inertia-static] The [%s] CDN driver needs [%s] to purge. Without credentials it only emits cache headers ("headers only" mode).',
            $driver,
            implode(', ', $keys),
        ));
    }
}
