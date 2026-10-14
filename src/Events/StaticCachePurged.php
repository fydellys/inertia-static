<?php

namespace Fydellys\InertiaStatic\Events;

use Illuminate\Foundation\Events\Dispatchable;

class StaticCachePurged
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $items
     */
    public function __construct(public string $operation, public array $items, public string $driver) {}
}
