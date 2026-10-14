<?php

namespace Fydellys\InertiaStatic\Support;

use Fydellys\InertiaStatic\Contracts\CdnDriver;
use Fydellys\InertiaStatic\Enums\CacheStatus;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a CachePolicy to a response: strips cookies, emits the driver's
 * cache / tag headers, a strong ETag and answers If-None-Match with 304.
 */
class ResponseFinalizer
{
    public function __construct(protected Repository $config) {}

    public function finalize(Request $request, Response $response, CachePolicy $policy, CdnDriver $driver, bool $jsonEndpoint = false): Response
    {
        $this->stripCookies($response);

        if ($jsonEndpoint) {
            // The JSON has its own URL: varying on X-Inertia would only split
            // the cache of Vary-aware proxies (nginx, Varnish, Fastly).
            $this->removeVary($response, 'X-Inertia');
        }

        if (! $policy->cacheable) {
            return $this->noStore($response, $policy);
        }

        $this->stripPerRequestHeaders($response);

        foreach ($driver->cacheHeaders($policy) as $name => $value) {
            $response->headers->set($name, $value);
        }

        if ($policy->tags !== []) {
            foreach ($driver->tagHeaders($policy->tags) as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        $this->debugHeader($response, CacheStatus::Miss, $policy);
        $this->cacheHeader($response, true);

        $content = $response->getContent();

        if (is_string($content) && $content !== '' && $response->getStatusCode() === 200) {
            $response->setEtag(hash('xxh128', $content));
            $response->isNotModified($request);
        }

        return $response;
    }

    public function noStore(Response $response, CachePolicy $policy): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        foreach (['CDN-Cache-Control', 'Cloudflare-CDN-Cache-Control', 'Surrogate-Control', 'Surrogate-Key', 'Cache-Tag', 'Edge-Cache-Tag', 'CDN-Tag', 'xkey', 'X-Accel-Expires'] as $header) {
            $response->headers->remove($header);
        }

        $this->debugHeader($response, CacheStatus::Bypass, $policy);
        $this->cacheHeader($response, false);

        return $response;
    }

    /**
     * The stable header CDN rules are built on ("cacheable" / "bypass").
     */
    protected function cacheHeader(Response $response, bool $cacheable): void
    {
        if ($header = $this->config->get('inertia-static.cache_header')) {
            $response->headers->set((string) $header, $cacheable ? 'cacheable' : 'bypass');
        }
    }

    /**
     * A shared cache must never receive (or forward) a user's cookie.
     */
    public function stripCookies(Response $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        $response->headers->remove('Set-Cookie');

        $this->removeVary($response, 'Cookie');
    }

    /**
     * Headers identifying a single request (e.g. Inertia DevTools ids) must
     * not be stored and replayed by a shared cache.
     */
    protected function stripPerRequestHeaders(Response $response): void
    {
        foreach (array_keys($response->headers->all()) as $name) {
            if (str_starts_with((string) $name, 'x-inertia-devtools')) {
                $response->headers->remove((string) $name);
            }
        }
    }

    protected function removeVary(Response $response, string $header): void
    {
        $vary = $response->getVary();

        if ($vary === []) {
            return;
        }

        $vary = array_values(array_filter($vary, fn (string $value) => strcasecmp($value, $header) !== 0));

        $vary === [] ? $response->headers->remove('Vary') : $response->setVary($vary);
    }

    protected function debugHeader(Response $response, CacheStatus $status, CachePolicy $policy): void
    {
        $header = $this->config->get('inertia-static.debug_header');

        if (! $header) {
            return;
        }

        $value = $status->value.($policy->cacheable
            ? "; ttl={$policy->revalidate}"
            : '; reason='.preg_replace('/[^A-Za-z0-9:_.-]/', '-', (string) $policy->reason));

        $response->headers->set($header, $value);
    }
}
