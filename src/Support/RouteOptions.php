<?php

namespace Fydellys\InertiaStatic\Support;

use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;
use Fydellys\InertiaStatic\Enums\RenderMode;
use Illuminate\Routing\Route;

/**
 * The static configuration declared on a route (macro or attribute).
 */
final readonly class RouteOptions
{
    public const ACTION_KEY = 'inertia_static';

    /**
     * @param  class-string<StaticPathsProvider>|null  $paths
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $query
     */
    public function __construct(
        public RenderMode $mode,
        public ?int $revalidate = null,
        public ?int $staleWhileRevalidate = null,
        public ?int $staleIfError = null,
        public array $tags = [],
        public array $query = [],
        public ?string $paths = null,
        public ?int $expire = null,
        public ?string $downgraded = null,
    ) {}

    public static function fromRoute(?Route $route): ?self
    {
        $options = $route?->getAction(self::ACTION_KEY);

        if (! is_array($options) || ! isset($options['mode'])) {
            return null;
        }

        return self::fromArray($options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            mode: $options['mode'] instanceof RenderMode ? $options['mode'] : RenderMode::from($options['mode']),
            revalidate: $options['revalidate'] ?? null,
            staleWhileRevalidate: $options['stale_while_revalidate'] ?? null,
            staleIfError: $options['stale_if_error'] ?? null,
            tags: array_values($options['tags'] ?? []),
            query: array_values($options['query'] ?? []),
            paths: $options['paths'] ?? null,
            expire: $options['expire'] ?? null,
            downgraded: $options['downgraded'] ?? null,
        );
    }

    /**
     * Serializable form, stored in the route action (route:cache safe).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'revalidate' => $this->revalidate,
            'stale_while_revalidate' => $this->staleWhileRevalidate,
            'stale_if_error' => $this->staleIfError,
            'tags' => $this->tags,
            'query' => $this->query,
            'paths' => $this->paths,
            'expire' => $this->expire,
            'downgraded' => $this->downgraded,
        ];
    }
}
