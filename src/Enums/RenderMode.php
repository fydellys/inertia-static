<?php

namespace Fydellys\InertiaStatic\Enums;

enum RenderMode: string
{
    case Dynamic = 'dynamic';
    case Isr = 'isr';
    case Static = 'static';

    public function isCacheable(): bool
    {
        return $this !== self::Dynamic;
    }
}
