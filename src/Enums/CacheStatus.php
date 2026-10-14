<?php

namespace Fydellys\InertiaStatic\Enums;

enum CacheStatus: string
{
    /** Rendered at the origin and cacheable by the CDN. */
    case Miss = 'MISS';

    /** Rendered at the origin and explicitly NOT cacheable. */
    case Bypass = 'BYPASS';

    /** Produced by the static exporter. */
    case Static = 'STATIC';
}
