<?php

namespace Fydellys\InertiaStatic\Console;

use Fydellys\InertiaStatic\Support\Downgrades;
use Fydellys\InertiaStatic\Support\Manifest;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia-static:routes')]
class RoutesCommand extends Command
{
    protected $signature = 'inertia-static:routes
        {--json : Output the client manifest as JSON}
        {--reset-downgrades : Make the routes the leak detector turned dynamic static again}';

    protected $description = 'List the static / ISR routes with their mode and TTL';

    public function handle(Manifest $manifest, Downgrades $downgrades): int
    {
        if ($this->option('reset-downgrades')) {
            $downgrades->clear();
            $this->components->info('Downgrades cleared: the routes are static again on the next request (restart Octane / queue workers).');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $defaults = (array) config('inertia-static.defaults');

        $rows = $manifest->routes()->map(fn (array $entry) => [
            '/'.ltrim($entry['route']->uri(), '/'),
            $entry['route']->getName() ?? '',
            $entry['options']->mode->value.($entry['options']->downgraded ? " (downgraded: {$entry['options']->downgraded})" : ''),
            $entry['options']->revalidate ?? $defaults['revalidate'] ?? '-',
            $entry['options']->staleWhileRevalidate ?? $defaults['stale_while_revalidate'] ?? '-',
            implode(', ', $entry['options']->tags),
            $entry['options']->paths ?? '',
        ]);

        if ($rows->isEmpty()) {
            $this->components->info('No static routes registered.');

            return self::SUCCESS;
        }

        $this->table(['URI', 'Name', 'Mode', 'Revalidate', 'SWR', 'Tags', 'Paths'], $rows->all());

        return self::SUCCESS;
    }
}
