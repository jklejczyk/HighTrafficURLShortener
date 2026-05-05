<?php

use App\Models\Link;
use App\Services\LinkCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->service = app(LinkCacheService::class);
});

it('get() returns null for non-existent code and caches NOT_FOUND', function () {
    expect($this->service->get('nonexistent'))->toBeNull()
        ->and(Cache::get('link_nonexistent'))->toBe('NOT_FOUND');
});

it('get() returns null for expired link and caches NOT_FOUND', function () {
    Link::factory()->expired()->create(['short_code' => 'expired1']);
    Cache::flush();

    expect($this->service->get('expired1'))->toBeNull()
        ->and(Cache::get('link_expired1'))->toBe('NOT_FOUND');
});

it('get() returns URL on cache miss and populates cache', function () {
    Link::factory()->create([
        'short_code' => 'fresh',
        'original_url' => 'https://example.com',
        'expires_at' => null,
    ]);
    Cache::flush();

    expect($this->service->get('fresh'))->toBe('https://example.com')
        ->and(Cache::get('link_fresh'))->toBe('https://example.com');
});

it('get() returns cached URL without querying DB', function () {
    Cache::put('link_cached', 'https://cached.com', 3600);

    DB::enableQueryLog();
    $url = $this->service->get('cached');

    expect($url)->toBe('https://cached.com')
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('get() returns null without querying DB on negative cache hit', function () {
    Cache::put('link_missing', 'NOT_FOUND', 300);

    DB::enableQueryLog();
    $url = $this->service->get('missing');

    expect($url)->toBeNull()
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('get() does not crash for link with null expires_at', function () {
    Link::factory()->create(['short_code' => 'eternal', 'expires_at' => null]);
    Cache::flush();

    expect(fn () => $this->service->get('eternal'))->not->toThrow(Throwable::class);
});

it('store() caches URL of valid link', function () {
    $link = Link::factory()->make([
        'short_code' => 'fresh1',
        'original_url' => 'https://fresh.com',
        'expires_at' => now()->addHours(2),
    ]);

    $this->service->store($link);

    expect(Cache::get('link_fresh1'))->toBe('https://fresh.com');
});

it('store() does not cache an expired link', function () {
    $link = Link::factory()->expired()->make(['short_code' => 'gone']);

    $this->service->store($link);

    expect(Cache::has('link_gone'))->toBeFalse();
});

it('store() caches link with null expires_at', function () {
    $link = Link::factory()->make([
        'short_code' => 'eternal2',
        'original_url' => 'https://eternal.com',
        'expires_at' => null,
    ]);

    $this->service->store($link);

    expect(Cache::get('link_eternal2'))->toBe('https://eternal.com');
});

it('forget() removes cached entry', function () {
    Cache::put('link_test1', 'https://example.com', 3600);

    $this->service->forget('test1');

    expect(Cache::has('link_test1'))->toBeFalse();
});

it('forget() is idempotent for non-existent key', function () {
    expect(fn () => $this->service->forget('nonexistent'))
        ->not->toThrow(Throwable::class);
});
