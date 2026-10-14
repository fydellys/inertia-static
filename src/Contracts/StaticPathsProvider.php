<?php

namespace Fydellys\InertiaStatic\Contracts;

interface StaticPathsProvider
{
    /**
     * The route parameters of the pages to pre-render.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function paths(): iterable;
}
