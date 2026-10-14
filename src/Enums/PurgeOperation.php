<?php

namespace Fydellys\InertiaStatic\Enums;

enum PurgeOperation: string
{
    case Urls = 'urls';
    case Tags = 'tags';
    case Prefixes = 'prefixes';
    case All = 'all';
}
