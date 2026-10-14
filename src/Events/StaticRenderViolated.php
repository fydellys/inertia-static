<?php

namespace Fydellys\InertiaStatic\Events;

use Fydellys\InertiaStatic\Exceptions\StaticRenderViolation;
use Illuminate\Foundation\Events\Dispatchable;

class StaticRenderViolated
{
    use Dispatchable;

    public function __construct(public StaticRenderViolation $violation) {}
}
