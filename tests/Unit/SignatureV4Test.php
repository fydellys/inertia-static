<?php

use Fydellys\InertiaStatic\Cdn\Aws\SignatureV4;

it('matches the AWS SigV4 test suite (get-vanilla)', function () {
    $signer = new SignatureV4('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', 'us-east-1', 'service');

    $headers = $signer->sign('GET', 'https://example.amazonaws.com/', at: new DateTimeImmutable('2015-08-30T12:36:00Z'));

    expect($headers['Authorization'])->toBe(
        'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/service/aws4_request, '
        .'SignedHeaders=host;x-amz-date, Signature=5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31'
    )->and($headers['X-Amz-Date'])->toBe('20150830T123600Z');
});

it('signs the session token of temporary credentials', function () {
    $signer = new SignatureV4('AKID', 'secret', 'us-east-1', 'cloudfront', 'token');

    $headers = $signer->sign('POST', 'https://cloudfront.amazonaws.com/x', ['Content-Type' => 'text/xml'], '<a/>');

    expect($headers['X-Amz-Security-Token'])->toBe('token')
        ->and($headers['Authorization'])->toContain('SignedHeaders=content-type;host;x-amz-date;x-amz-security-token');
});
