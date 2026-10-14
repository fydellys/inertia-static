<?php

namespace Fydellys\InertiaStatic\Tests\Fixtures;

use Inertia\Ssr\Gateway;
use Inertia\Ssr\Response;

class FakeSsrGateway implements Gateway
{
    public static bool $available = true;

    public static int $calls = 0;

    public function dispatch(array $page): ?Response
    {
        static::$calls++;

        if (! static::$available) {
            return null;
        }

        $title = e($page['props']['title'] ?? $page['component']);

        return new Response(
            "<title inertia>{$title}</title>\n<meta name=\"description\" content=\"{$title}\" inertia>",
            "<div id=\"app\" data-page=\"app\"><h1>{$title}</h1></div>",
        );
    }
}
