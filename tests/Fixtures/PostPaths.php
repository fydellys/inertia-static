<?php

namespace Fydellys\InertiaStatic\Tests\Fixtures;

use Fydellys\InertiaStatic\Contracts\StaticPathsProvider;

class PostPaths implements StaticPathsProvider
{
    public function paths(): iterable
    {
        yield ['slug' => 'hello-world'];
        yield ['slug' => 'second-post'];
    }
}
