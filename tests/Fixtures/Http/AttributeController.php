<?php

namespace Fydellys\InertiaStatic\Tests\Fixtures\Http;

use Fydellys\InertiaStatic\Attributes\StaticPage;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController
{
    #[StaticPage(mode: 'isr', revalidate: 120, tags: ['attr'])]
    public function show(): Response
    {
        return Inertia::render('Attr/Show', ['title' => 'Attribute']);
    }
}
