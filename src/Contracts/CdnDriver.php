<?php

namespace Fydellys\InertiaStatic\Contracts;

use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Support\CachePolicy;

interface CdnDriver
{
    /**
     * Headers that make the response cacheable at the edge for the policy.
     *
     * @return array<string, string>
     */
    public function cacheHeaders(CachePolicy $policy): array;

    /**
     * Headers that attach cache tags to the response.
     *
     * @param  array<int, string>  $tags
     * @return array<string, string>
     */
    public function tagHeaders(array $tags): array;

    /**
     * @param  array<int, string>  $urls  Absolute URLs.
     */
    public function purgeUrls(array $urls): void;

    /**
     * @param  array<int, string>  $tags
     */
    public function purgeTags(array $tags): void;

    /**
     * @param  array<int, string>  $prefixes  Absolute URL prefixes.
     */
    public function purgePrefixes(array $prefixes): void;

    public function purgeAll(): void;

    public function supports(Capability $capability): bool;

    /**
     * Whether any purge operation is available (credentials configured).
     */
    public function canPurge(): bool;

    /**
     * Maximum number of items accepted by a single purge call.
     */
    public function batchSize(Capability $capability): int;
}
