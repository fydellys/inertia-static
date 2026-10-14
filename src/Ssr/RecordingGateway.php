<?php

namespace Fydellys\InertiaStatic\Ssr;

use Illuminate\Contracts\Container\Container;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HasHealthCheck;
use Inertia\Ssr\Response;

/**
 * Inertia 2 only (Inertia 3 has SsrState): records what the SSR gateway rendered.
 */
class RecordingGateway implements Gateway, HasHealthCheck
{
    public function __construct(
        protected Gateway $gateway,
        protected Container $container,
    ) {}

    public function dispatch(array $page): ?Response
    {
        $response = $this->gateway->dispatch($page);

        $this->container->make(SsrRecorder::class)->record($page, $response);

        return $response;
    }

    public function isHealthy(): bool
    {
        return ! $this->gateway instanceof HasHealthCheck || $this->gateway->isHealthy();
    }

    public function inner(): Gateway
    {
        return $this->gateway;
    }
}
