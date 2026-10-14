<?php

namespace Fydellys\InertiaStatic\Ssr;

use Illuminate\Contracts\Container\Container;
use Inertia\Ssr\Response;
use Inertia\Ssr\SsrState;

/**
 * The page rendered in this request and the SSR result, on both Inertia majors:
 * Inertia 3 keeps them in SsrState; on Inertia 2, RecordingGateway records them here.
 */
class SsrRecorder
{
    /** @var array<string, mixed>|null */
    protected ?array $page = null;

    protected ?Response $response = null;

    public function __construct(protected Container $container) {}

    public static function nativeState(): bool
    {
        return class_exists(SsrState::class);
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public function record(array $page, ?Response $response): void
    {
        $this->page = $page;
        $this->response = $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function page(): ?array
    {
        return static::nativeState() ? $this->container->make(SsrState::class)->page : $this->page;
    }

    public function response(): ?Response
    {
        return static::nativeState() ? $this->container->make(SsrState::class)->response : $this->response;
    }
}
