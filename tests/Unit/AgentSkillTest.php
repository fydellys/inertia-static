<?php

// The agent skill ships everything it needs: no file to fetch from a URL at runtime.

$skill = __DIR__.'/../../resources/boost/skills/inertia-static-development';

it('bundles the same Varnish VCL as the docs', function () use ($skill) {
    expect(file_get_contents("{$skill}/references/varnish.vcl"))->toBe(file_get_contents(__DIR__.'/../../docs/varnish.vcl'));
});

it('links no file on GitHub or any other host to download', function () use ($skill) {
    $files = [...glob("{$skill}/*.md"), ...glob("{$skill}/references/*.md"), __DIR__.'/../../resources/boost/guidelines/core.blade.php'];

    foreach ($files as $file) {
        expect(file_get_contents($file))
            ->not->toMatch('~https?://(github\.com|raw\.githubusercontent\.com|gist\.github)~')
            ->not->toMatch('~\b(curl|wget)\b[^\n]*\|\s*(sh|bash)\b~');
    }
});
