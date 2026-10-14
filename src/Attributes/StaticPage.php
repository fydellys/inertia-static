<?php

namespace Fydellys\InertiaStatic\Attributes;

use Attribute;
use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;
use Fydellys\InertiaStatic\Enums\RenderMode;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class StaticPage
{
    /**
     * @param  class-string<StaticPathsProvider>|null  $paths
     * @param  array<int, string>  $tags
     * @param  array<int, string>  $query
     */
    public function __construct(
        public RenderMode|string $mode = RenderMode::Isr,
        public ?int $revalidate = null,
        public ?string $paths = null,
        public array $tags = [],
        public array $query = [],
        public ?int $staleWhileRevalidate = null,
        public ?int $staleIfError = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOptions(): array
    {
        return [
            'revalidate' => $this->revalidate,
            'paths' => $this->paths,
            'tags' => $this->tags,
            'query' => $this->query,
            'stale_while_revalidate' => $this->staleWhileRevalidate,
            'stale_if_error' => $this->staleIfError,
        ];
    }

    public function renderMode(): RenderMode
    {
        return $this->mode instanceof RenderMode ? $this->mode : RenderMode::from($this->mode);
    }
}
