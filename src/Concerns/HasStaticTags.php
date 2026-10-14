<?php

namespace Fydellys\InertiaStatic\Concerns;

use Fydellys\InertiaStatic\Facades\StaticCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Purges the model's cache tags whenever it is saved, deleted or restored.
 * Identical purges inside the debounce window are collapsed into one job.
 *
 * @mixin Model
 */
trait HasStaticTags
{
    public static function bootHasStaticTags(): void
    {
        foreach (['saved', 'deleted', 'restored'] as $event) {
            if (method_exists(static::class, $event)) {
                static::{$event}(fn (self $model) => StaticCache::purgeTags($model->staticTags()));
            }
        }
    }

    /**
     * The tags of the pages that render this model, e.g. ["posts", "post:1"].
     *
     * @return array<int, string>
     */
    public function staticTags(): array
    {
        $table = $this->getTable();

        return [$table, Str::singular($table).':'.$this->getKey()];
    }
}
