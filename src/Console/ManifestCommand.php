<?php

namespace Fydellys\InertiaStatic\Console;

use Fydellys\InertiaStatic\Support\Manifest;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia-static:manifest')]
class ManifestCommand extends Command
{
    protected $signature = 'inertia-static:manifest
        {--path= : Where to write the manifest (default: resources/js/inertia-static.json)}';

    protected $description = 'Write the static route manifest consumed by the client plugin';

    public function handle(Manifest $manifest, Filesystem $files): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : resource_path('js/inertia-static.json');

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, json_encode($manifest->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->components->info("Manifest written to [{$path}].");

        return self::SUCCESS;
    }
}
