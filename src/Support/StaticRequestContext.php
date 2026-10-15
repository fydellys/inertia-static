<?php

namespace Fydellys\InertiaStatic\Support;

use Fydellys\InertiaStatic\Exceptions\StaticRenderViolation;
use Illuminate\Routing\Route;

/**
 * Per-request state (bound as "scoped", so it is reset for every request
 * and every Octane / queue lifecycle).
 */
class StaticRequestContext
{
    public ?RouteOptions $route = null;

    public bool $jsonEndpoint = false;

    /** The response is a redirect to the canonical URL (always cacheable). */
    public bool $canonicalRedirect = false;

    /** A valid preview cookie was sent. */
    public bool $preview = false;

    /** The page path served by the JSON endpoint ("/blog/post"). */
    public ?string $pagePath = null;

    public bool $bypass = false;

    public ?string $bypassReason = null;

    /** @var array{route: Route, reason: string}|null leak "auto" mode, persisted after a successful render */
    public ?array $pendingDowngrade = null;

    /** @var array<string, int|null> */
    protected array $overrides = [];

    /** @var array<int, string> */
    protected array $tags = [];

    /** @var array<int, StaticRenderViolation> */
    protected array $violations = [];

    /**
     * Forget everything about a previous request (the binding is scoped, but
     * the same container can serve several requests, e.g. in tests).
     */
    public function reset(): void
    {
        $this->route = null;
        $this->jsonEndpoint = false;
        $this->pagePath = null;
        $this->canonicalRedirect = false;
        $this->preview = false;
        $this->bypass = false;
        $this->bypassReason = null;
        $this->pendingDowngrade = null;
        $this->overrides = [];
        $this->tags = [];
        $this->violations = [];
    }

    public function activate(RouteOptions $route): void
    {
        $this->route = $route;
    }

    public function isActive(): bool
    {
        return $this->route !== null && $this->route->mode->isCacheable();
    }

    public function bypass(string $reason): void
    {
        $this->bypass = true;
        $this->bypassReason ??= $reason;
    }

    public function override(string $key, ?int $value): void
    {
        $this->overrides[$key] = $value;
    }

    public function overridden(string $key, ?int $default): ?int
    {
        return array_key_exists($key, $this->overrides) ? $this->overrides[$key] : $default;
    }

    /**
     * @param  array<int, string>|string  $tags
     */
    public function addTags(array|string $tags): void
    {
        $this->tags = array_values(array_unique([...$this->tags, ...(array) $tags]));
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return array_values(array_unique([...($this->route->tags ?? []), ...$this->tags]));
    }

    public function recordViolation(StaticRenderViolation $violation): void
    {
        $this->violations[] = $violation;
    }

    /**
     * @return array<int, StaticRenderViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }
}
