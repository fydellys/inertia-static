<?php

namespace Fydellys\InertiaStatic\Contracts;

interface StaticPathsProvider
{
    /**
     * The route parameters to pre-render (equivalent to getStaticPaths).
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function paths(): iterable;
}
