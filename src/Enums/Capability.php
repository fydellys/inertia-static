<?php

namespace Fydellys\InertiaStatic\Enums;

enum Capability: string
{
    case PurgeUrls = 'purge_urls';
    case PurgeTags = 'purge_tags';
    case PurgePrefix = 'purge_prefix';
    case PurgeAll = 'purge_all';
    case CacheTags = 'cache_tags';
}
