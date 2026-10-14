<?php

namespace Fydellys\InertiaStatic\Cdn\Drivers;

use Fydellys\InertiaStatic\Cdn\Aws\SignatureV4;
use Fydellys\InertiaStatic\Cdn\Driver;
use Fydellys\InertiaStatic\Enums\Capability;
use Fydellys\InertiaStatic\Support\CachePolicy;
use Illuminate\Support\Str;

/**
 * Amazon CloudFront: s-maxage in Cache-Control, purges through invalidations
 * (paths, "*" for prefixes). No cache tags: tag purges go through the registry.
 */
class CloudFrontDriver extends Driver
{
    public const API = 'https://cloudfront.amazonaws.com/2020-05-31';

    protected array $capabilities = [
        Capability::PurgeUrls,
        Capability::PurgePrefix,
        Capability::PurgeAll,
    ];

    protected array $credentials = ['distribution_id', 'key', 'secret'];

    public function name(): string
    {
        return 'cloudfront';
    }

    /**
     * CloudFront has no edge-only header. stale-while-revalidate in Cache-Control would
     * reach browsers too, so it is only sent when a response headers policy replaces the
     * viewer's Cache-Control ("stale" => true).
     */
    public function cacheHeaders(CachePolicy $policy): array
    {
        $directives = ['public', 'max-age=0', "s-maxage={$policy->revalidate}"];

        if ($this->config['stale'] ?? false) {
            $directives = ['public', 'max-age=0', ...$policy->edgeDirectives('s-maxage')];
        }

        return ['Cache-Control' => implode(', ', $directives)];
    }

    /**
     * Up to 3,000 paths per invalidation, and 15 wildcard paths in progress.
     */
    public function batchSize(Capability $capability): int
    {
        return $capability === Capability::PurgePrefix ? 15 : min((int) ($this->config['batch_size'] ?? 1000), 3000);
    }

    public function purgeUrls(array $urls): void
    {
        $this->invalidate(array_map(fn (string $url) => $this->pathOf($url), $urls));
    }

    public function purgePrefixes(array $prefixes): void
    {
        $this->invalidate(array_map(fn (string $prefix) => Str::finish($this->pathOf($prefix), '*'), $prefixes));
    }

    public function purgeAll(): void
    {
        $this->invalidate(['/*']);
    }

    protected function pathOf(string $url): string
    {
        $parts = parse_url($url) ?: [];
        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');

        return isset($parts['query']) ? "{$path}?{$parts['query']}" : $path;
    }

    /**
     * @param  array<int, string>  $paths
     */
    protected function invalidate(array $paths): void
    {
        $this->ensureCredentials();

        $paths = array_values(array_unique($paths));
        $items = implode('', array_map(fn (string $path) => '<Path>'.htmlspecialchars($path, ENT_XML1).'</Path>', $paths));
        $body = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<InvalidationBatch xmlns="http://cloudfront.amazonaws.com/doc/2020-05-31/">'
            .'<Paths><Quantity>'.count($paths).'</Quantity><Items>'.$items.'</Items></Paths>'
            .'<CallerReference>inertia-static-'.Str::uuid().'</CallerReference>'
            .'</InvalidationBatch>';

        $url = self::API.'/distribution/'.rawurlencode((string) $this->config['distribution_id']).'/invalidation';
        $signer = new SignatureV4(
            (string) $this->config['key'],
            (string) $this->config['secret'],
            'us-east-1', // CloudFront is a global service signed in us-east-1
            'cloudfront',
            isset($this->config['token']) ? (string) $this->config['token'] : null,
        );

        // One signature covers the retries: AWS accepts a 15-minute clock skew.
        $this->request()
            ->withHeaders($signer->sign('POST', $url, ['Content-Type' => 'text/xml'], $body))
            ->withBody($body, 'text/xml')
            ->post($url)
            ->throw();
    }
}
