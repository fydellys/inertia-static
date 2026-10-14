<?php

namespace Fydellys\InertiaStatic\Enums;

/**
 * Soft: mark content stale (served while it is refreshed). Hard: remove it now.
 */
enum PurgeMode: string
{
    /** Mark stale: the CDN keeps serving the old copy while it refetches (where supported). */
    case Soft = 'soft';

    /** Remove: the next request is a miss. */
    case Hard = 'hard';
}
