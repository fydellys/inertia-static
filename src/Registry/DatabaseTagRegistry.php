<?php

namespace Fydellys\InertiaStatic\Registry;

use Fydellys\InertiaStatic\Contracts\TagRegistry;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

/**
 * Stores tag => URL pairs in a table (see the published migration).
 * URLs are stored as relative page paths, so they survive asset versions.
 */
class DatabaseTagRegistry implements TagRegistry
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected string $table = 'inertia_static_tags',
    ) {}

    public function record(array $tags, array $urls): void
    {
        $rows = [];
        $now = now();

        foreach (array_unique($tags) as $tag) {
            foreach (array_unique($urls) as $url) {
                $rows[] = [
                    'tag' => $tag,
                    'tag_hash' => $this->hash($tag),
                    'url' => $url,
                    'url_hash' => $this->hash($url),
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            $this->query()->upsert($rows, ['tag_hash', 'url_hash'], ['updated_at']);
        }
    }

    public function urlsFor(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        return $this->query()->whereIn('tag_hash', $this->hashes($tags))->distinct()->orderBy('url')->pluck('url')->all();
    }

    public function urlsWithPrefix(array $prefixes): array
    {
        if ($prefixes === []) {
            return [];
        }

        return $this->query()
            ->where(function (Builder $query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('url', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], Str::start($prefix, '/')).'%');
                }
            })
            ->distinct()
            ->orderBy('url')
            ->pluck('url')
            ->all();
    }

    public function forget(array $tags): void
    {
        if ($tags !== []) {
            $this->query()->whereIn('tag_hash', $this->hashes($tags))->delete();
        }
    }

    public function flush(): void
    {
        $this->query()->delete();
    }

    protected function hash(string $value): string
    {
        return hash('xxh128', $value);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    protected function hashes(array $values): array
    {
        return array_map($this->hash(...), array_values(array_unique($values)));
    }

    protected function query(): Builder
    {
        return $this->connection->table($this->table);
    }
}
