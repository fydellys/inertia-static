<?php

namespace Fydellys\InertiaStatic\Support;

/**
 * Implicit path tags: every cached page is
 * tagged with each path prefix it lives under, so a prefix can be purged with
 * a tag purge on CDNs without prefix purging (e.g. Fastly).
 *
 *   /blog/hello  =>  _IST_/  _IST_/blog/  _IST_/blog/hello/
 */
final class ImplicitTags
{
    public const PREFIX = '_IST_';

    public const MAX_DEPTH = 8;

    /**
     * @return array<int, string>
     */
    public static function forPath(string $path): array
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($path, PHP_URL_PATH))));
        $tags = [self::PREFIX.'/'];
        $current = '';

        foreach (array_slice($segments, 0, self::MAX_DEPTH) as $segment) {
            $current .= '/'.$segment;
            $tags[] = self::PREFIX.$current.'/';
        }

        return $tags;
    }

    /**
     * The tag that covers every page under a prefix ("/blog" or "/blog/").
     */
    public static function forPrefix(string $prefix): string
    {
        $path = trim((string) parse_url($prefix, PHP_URL_PATH), '/');

        return self::PREFIX.'/'.($path === '' ? '' : $path.'/');
    }

    public static function isImplicit(string $tag): bool
    {
        return str_starts_with($tag, self::PREFIX);
    }
}
