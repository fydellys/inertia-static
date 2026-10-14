<?php

namespace Fydellys\InertiaStatic\Contracts;

interface TagRegistry
{
    /**
     * Associate the given URLs with the tags.
     *
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $urls
     */
    public function record(array $tags, array $urls): void;

    /**
     * Expand tags into the URLs that were cached with them.
     *
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    public function urlsFor(array $tags): array;

    /**
     * Every registered URL starting with one of the prefixes.
     *
     * @param  array<int, string>  $prefixes
     * @return array<int, string>
     */
    public function urlsWithPrefix(array $prefixes): array;

    /**
     * @param  array<int, string>  $tags
     */
    public function forget(array $tags): void;

    public function flush(): void;
}
