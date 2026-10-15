<?php

namespace Fydellys\InertiaStatic\Cdn\Aws;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * AWS Signature Version 4 for a single request (no SDK dependency).
 */
class SignatureV4
{
    public function __construct(
        protected string $accessKey,
        protected string $secretKey,
        protected string $region,
        protected string $service,
        protected ?string $sessionToken = null,
    ) {}

    /**
     * Headers to add to the request (Authorization, X-Amz-Date, ...).
     *
     * @param  array<string, string>  $headers  headers that must be signed (Host is derived from the URL)
     * @return array<string, string>
     */
    public function sign(string $method, string $url, array $headers = [], string $body = '', ?DateTimeInterface $at = null): array
    {
        $at = DateTimeImmutable::createFromInterface($at ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
        $amzDate = $at->format('Ymd\THis\Z');
        $date = $at->format('Ymd');
        $parts = parse_url($url) ?: [];

        $headers = array_change_key_case($headers, CASE_LOWER) + ['host' => (string) ($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '')];
        $headers['x-amz-date'] = $amzDate;

        if ($this->sessionToken !== null && $this->sessionToken !== '') {
            $headers['x-amz-security-token'] = $this->sessionToken;
        }

        ksort($headers);
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = implode('', array_map(
            fn (string $name, string $value) => $name.':'.trim((string) preg_replace('/\s+/', ' ', $value))."\n",
            array_keys($headers),
            $headers,
        ));

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $this->canonicalPath((string) ($parts['path'] ?? '/')),
            $this->canonicalQuery((string) ($parts['query'] ?? '')),
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $body),
        ]);

        $scope = "{$date}/{$this->region}/{$this->service}/aws4_request";
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

        $key = hash_hmac('sha256', $date, 'AWS4'.$this->secretKey, true);
        $key = hash_hmac('sha256', $this->region, $key, true);
        $key = hash_hmac('sha256', $this->service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);

        unset($headers['host']);
        $headers['authorization'] = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature=".hash_hmac('sha256', $stringToSign, $key);

        // Usual casing (X-Amz-Date, Authorization): names are signed in lowercase only.
        $named = [];

        foreach ($headers as $name => $value) {
            $named[implode('-', array_map('ucfirst', explode('-', $name)))] = $value;
        }

        return $named;
    }

    protected function canonicalPath(string $path): string
    {
        return implode('/', array_map(fn (string $segment) => rawurlencode(rawurldecode($segment)), explode('/', $path === '' ? '/' : $path)));
    }

    protected function canonicalQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = array_map(function (string $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            return rawurlencode(rawurldecode($name)).'='.rawurlencode(rawurldecode($value));
        }, explode('&', $query));

        sort($pairs);

        return implode('&', $pairs);
    }
}
