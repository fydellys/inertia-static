<?php

namespace Fydellys\InertiaStatic\Exceptions;

use RuntimeException;

class StaticRenderViolation extends RuntimeException
{
    public function __construct(public readonly string $type, public readonly string $detail, public readonly ?string $url = null)
    {
        parent::__construct(sprintf(
            '[inertia-static] Private data accessed while rendering a static page%s: %s (%s). Static pages are shared by every visitor; move user-specific data to a dynamic "island" endpoint.',
            $url ? " [{$url}]" : '',
            $detail,
            $type,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['type' => $this->type, 'detail' => $this->detail, 'url' => $this->url];
    }
}
