<?php

namespace Fydellys\InertiaStatic\Console;

use Fydellys\InertiaStatic\Jobs\WarmStaticCache;
use Fydellys\InertiaStatic\Support\PathCollector;
use Fydellys\InertiaStatic\Support\StaticUrls;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia-static:warm')]
class WarmCommand extends Command
{
    protected $signature = 'inertia-static:warm
        {--only=* : Only routes whose name or URI matches (wildcards allowed)}
        {--concurrency=10 : Parallel requests}
        {--limit=0 : Max pages per route (0 = provider limit)}
        {--dry-run : List the URLs without requesting them}';

    protected $description = 'Warm the CDN cache (HTML + JSON) of every static page';

    public function handle(PathCollector $paths, StaticUrls $urls): int
    {
        $pages = $paths->collect((array) $this->option('only'), (int) $this->option('limit'));
        $targets = $urls->variants($pages);

        if ($this->option('dry-run')) {
            foreach ($targets as $url) {
                $this->line($url);
            }

            return self::SUCCESS;
        }

        $statuses = (new WarmStaticCache($targets, (int) $this->option('concurrency')))->handle();

        $failed = array_filter($statuses, fn (int $status) => $status < 200 || $status >= 400);

        $this->components->info(sprintf('Warmed %d URLs (%d failed).', count($statuses), count($failed)));

        foreach ($failed as $url => $status) {
            $this->components->warn("{$status} {$url}");
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
