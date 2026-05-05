<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;

it('deletes link via DELETE', function () {
    $link = Link::factory()->create(['short_code' => 'del1']);

    deleteJson("/api/shorten/{$link->short_code}")->assertNoContent();

    expect(Link::query()->where('short_code', 'del1')->exists())->toBeFalse();
});

it('returns 404 for unknown code', function () {
    deleteJson('/api/shorten/missing')->assertNotFound();
});

it('returns 403 when deleting someone else link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->forUser($owner)->create(['short_code' => 'protected']);

    actingAs($other)
        ->deleteJson("/api/shorten/{$link->short_code}")
        ->assertForbidden();

    expect(Link::query()->where('short_code', 'protected')->exists())->toBeTrue();
});

it('allows owner to delete their own link', function () {
    $owner = User::factory()->create();
    $link = Link::factory()->forUser($owner)->create(['short_code' => 'mine2']);

    actingAs($owner)
        ->deleteJson("/api/shorten/{$link->short_code}")
        ->assertNoContent();

    expect(Link::query()->where('short_code', 'mine2')->exists())->toBeFalse();
});

it('invalidates cache after delete via observer', function () {
    $link = Link::factory()->create([
        'short_code' => 'cache2',
        'original_url' => 'https://example.com',
        'expires_at' => null,
    ]);

    expect(Cache::get('link_cache2'))->toBe('https://example.com');

    deleteJson("/api/shorten/{$link->short_code}")->assertNoContent();

    expect(Cache::has('link_cache2'))->toBeFalse();
});
