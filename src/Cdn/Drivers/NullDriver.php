<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;

/**
 * Emits standard headers and silently accepts every purge.
 */
class NullDriver extends Driver
{
    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgeTags,
        Capability::PurgePrefix,
        Capability::PurgeAll,
    ];

    public function name(): string
    {
        return 'null';
    }

    public function purgeUrls(array $urls): void {}

    public function purgeTags(array $tags): void {}

    public function purgePrefixes(array $prefixes): void {}

    public function purgeAll(): void {}
}
