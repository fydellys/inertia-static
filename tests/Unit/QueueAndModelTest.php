<?php

use Fydellys\InertiaStatic\Concerns\HasStaticTags;
use Fydellys\InertiaStatic\Enums\PurgeOperation;
use Fydellys\InertiaStatic\Events\StaticCachePurged;
use Fydellys\InertiaStatic\Facades\StaticCache;
use Fydellys\InertiaStatic\Jobs\PurgeStaticCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class StaticPost extends Model
{
    use HasStaticTags;

    protected $table = 'posts';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });
});

it('dispatches a single debounced purge for repeated model events', function () {
    Queue::fake();

    $post = StaticPost::create(['title' => 'a']);
    $post->update(['title' => 'b']);
    $post->update(['title' => 'c']);
    $post->delete();

    Queue::assertPushed(PurgeStaticCache::class, 1);
    Queue::assertPushed(PurgeStaticCache::class, fn (PurgeStaticCache $job) => $job->operation === PurgeOperation::Tags
        && $job->items === ['post:1', 'posts']);
});

it('queues facade purges with delay, rate limit and backoff', function () {
    Queue::fake();
    config(['inertia-static.purge.debounce' => 10, 'inertia-static.purge.queue' => 'cdn']);

    StaticCache::purgeTags(['post:1']);
    StaticCache::purgeUrls('/blog');
    StaticCache::purgePrefix('/docs');
    StaticCache::purgeAll();

    Queue::assertPushedOn('cdn', PurgeStaticCache::class);
    Queue::assertPushed(PurgeStaticCache::class, 4);
    Queue::assertPushed(PurgeStaticCache::class, function (PurgeStaticCache $job) {
        return $job->delay !== null
            && $job->middleware()[0] instanceof RateLimited
            && $job->backoff() === [5, 30, 120]
            && $job->maxExceptions === 5;
    });
});

it('skips empty purges and respects the kill switch', function () {
    Queue::fake();

    StaticCache::purgeTags([]);
    config(['inertia-static.enabled' => false]);
    StaticCache::purgeTags(['x']);

    Queue::assertNothingPushed();
});

it('runs the purge command synchronously', function () {
    Event::fake([StaticCachePurged::class]);

    $this->artisan('inertia-static:purge', ['--tags' => ['posts,post:1'], '--url' => ['/blog']])->assertSuccessful();

    Event::assertDispatched(StaticCachePurged::class, 2);
});

it('requires a purge target', function () {
    $this->artisan('inertia-static:purge')->assertFailed();
});

it('skips queued purges in "headers only" mode', function () {
    Queue::fake();
    config(['inertia-static.cdn' => 'cloudflare', 'inertia-static.drivers.cloudflare' => ['driver' => 'cloudflare']]);

    expect(StaticCache::canPurge())->toBeFalse();

    StaticCache::purgeTags(['post:1']);
    StaticCache::purgeAll();

    Queue::assertNothingPushed();
});

it('refuses the purge command in "headers only" mode', function () {
    config(['inertia-static.cdn' => 'cloudflare', 'inertia-static.drivers.cloudflare' => ['driver' => 'cloudflare']]);

    $this->artisan('inertia-static:purge', ['--all' => true])
        ->expectsOutputToContain('headers only')
        ->assertFailed();
});
