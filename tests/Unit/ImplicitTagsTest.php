<?php

use Fydellys\InertiaStatic\Support\ImplicitTags;

it('tags every path prefix of a page', function () {
    expect(ImplicitTags::forPath('/blog/hello'))->toBe(['_IST_/', '_IST_/blog/', '_IST_/blog/hello/'])
        ->and(ImplicitTags::forPath('/'))->toBe(['_IST_/'])
        ->and(ImplicitTags::forPath('/blog?page=2'))->toBe(['_IST_/', '_IST_/blog/']);
});

it('maps a prefix to its tag', function () {
    expect(ImplicitTags::forPrefix('/blog'))->toBe('_IST_/blog/')
        ->and(ImplicitTags::forPrefix('/blog/'))->toBe('_IST_/blog/')
        ->and(ImplicitTags::forPrefix('https://example.com/docs/v2'))->toBe('_IST_/docs/v2/')
        ->and(ImplicitTags::forPrefix('/'))->toBe('_IST_/');
});
