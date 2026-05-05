<?php

use App\Models\Link;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

it('redirects to original url with status 302', function () {
    $link = Link::factory()->create([
        'short_code' => 'abc1234',
        'original_url' => 'https://google.com',
    ]);

    get("/{$link->short_code}")
        ->assertStatus(302)
        ->assertRedirect('https://google.com');
});

it('returns 404 for an unknown code', function () {
    get('/unknown')->assertNotFound();
});

it('returns 404 for an expired link', function () {
    $link = Link::factory()->expired()->create(['short_code' => 'expired']);

    get("/{$link->short_code}")->assertNotFound();
});

it('returns 302 for a link expiring in the future', function () {
    $link = Link::factory()->expiringSoon()->create([
        'short_code' => 'future',
        'original_url' => 'https://example.com',
    ]);

    get("/{$link->short_code}")->assertStatus(302);
});

it('does not query DB on cache hit', function () {
    $link = Link::factory()->create([
        'short_code' => 'cached',
        'original_url' => 'https://cached.com',
        'expires_at' => null,
    ]);

    Cache::flush();
    get("/{$link->short_code}");

    DB::enableQueryLog();
    get("/{$link->short_code}")->assertStatus(302);

    expect(DB::getQueryLog())->toBeEmpty();
});

it('does not query DB on negative cache hit', function () {
    Cache::flush();
    get('/missing1');

    DB::enableQueryLog();
    get('/missing1')->assertNotFound();

    expect(DB::getQueryLog())->toBeEmpty();
});