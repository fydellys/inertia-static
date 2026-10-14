<?php

namespace Fydellys\InertiaStatic\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Preview mode: a signed cookie that makes static
 * pages skip the cache and render fresh (for drafts, unpublished content).
 *
 * The cookie is not HttpOnly: the client plugin reads it to send SPA visits to
 * the never-cached live endpoint. It is signed (HMAC with the app key), so it
 * cannot be forged, and it only grants "skip the cache", never any access.
 */
class Preview
{
    public function __construct(protected Repository $config) {}

    public function cookieName(): string
    {
        return (string) $this->config->get('inertia-static.preview.cookie', 'inertia_static_preview');
    }

    public function enable(?int $minutes = null): Cookie
    {
        $minutes ??= (int) $this->config->get('inertia-static.preview.minutes', 60);
        $expires = time() + $minutes * 60;

        return Cookie::create($this->cookieName(), $expires.'.'.$this->sign((string) $expires), $expires, '/', null, null, false, false, 'lax');
    }

    public function disable(): Cookie
    {
        return Cookie::create($this->cookieName(), '', 1, '/', null, null, false, false, 'lax');
    }

    public function isActive(Request $request): bool
    {
        $value = (string) $request->cookies->get($this->cookieName(), '');

        if (! str_contains($value, '.')) {
            return false;
        }

        [$expires, $signature] = explode('.', $value, 2);

        return ctype_digit($expires)
            && (int) $expires > time()
            && hash_equals($this->sign($expires), $signature);
    }

    protected function sign(string $value): string
    {
        return hash_hmac('sha256', 'inertia-static-preview|'.$value, (string) $this->config->get('app.key'));
    }
}
