<?php

namespace Fydellys\InertiaStatic\Support;

/**
 * Immutable description of how a single response may be cached.
 */
final readonly class CachePolicy
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public bool $cacheable,
        public int $revalidate = 0,
        public ?int $staleWhileRevalidate = null,
        public ?int $staleIfError = null,
        public array $tags = [],
        public string $browserCacheControl = 'public, max-age=0, must-revalidate',
        public ?string $reason = null,
    ) {}

    public static function noStore(string $reason): self
    {
        return new self(cacheable: false, reason: $reason);
    }

    public function withTtl(int $seconds): self
    {
        return new self(
            $this->cacheable,
            $seconds,
            $this->staleWhileRevalidate,
            $this->staleIfError,
            $this->tags,
            $this->browserCacheControl,
            $this->reason,
        );
    }

    /**
     * The edge directives shared by every CDN header style.
     *
     * @return array<int, string>
     */
    public function edgeDirectives(string $maxAgeDirective = 'max-age'): array
    {
        return array_values(array_filter([
            "{$maxAgeDirective}={$this->revalidate}",
            $this->staleWhileRevalidate ? "stale-while-revalidate={$this->staleWhileRevalidate}" : null,
            $this->staleIfError ? "stale-if-error={$this->staleIfError}" : null,
        ]));
    }
}
