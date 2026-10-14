<?php

namespace Fydellys\InertiaStatic\Registry;

use Fydellys\InertiaStatic\Contracts\TagRegistry;

class NullTagRegistry implements TagRegistry
{
    public function record(array $tags, array $urls): void {}

    public function urlsFor(array $tags): array
    {
        return [];
    }

    public function urlsWithPrefix(array $prefixes): array
    {
        return [];
    }

    public function forget(array $tags): void {}

    public function flush(): void {}
}
