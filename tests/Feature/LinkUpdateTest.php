<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;

it('updates url via PATCH', function () {
    $link = Link::factory()->create([
        'short_code' => 'upd1',
        'original_url' => 'https://old.com',
    ]);

    patchJson("/api/shorten/{$link->short_code}", ['url' => 'https://new.com'])
        ->assertOk()
        ->assertJson(['original_url' => 'https://new.com']);

    expect($link->fresh()->original_url)->toBe('https://new.com');
});

it('returns 422 for invalid url', function () {
    $link = Link::factory()->create(['short_code' => 'badup']);

    patchJson("/api/shorten/{$link->short_code}", ['url' => 'not-a-url'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('returns 404 for unknown code', function () {
    patchJson('/api/shorten/missing', ['url' => 'https://example.com'])
        ->assertNotFound();
});

it('returns 403 when updating someone else link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->forUser($owner)->create(['short_code' => 'owned']);

    actingAs($other)
        ->patchJson("/api/shorten/{$link->short_code}", ['url' => 'https://hacker.com'])
        ->assertForbidden();

    expect($link->fresh()->original_url)->not->toBe('https://hacker.com');
});

it('allows update of anonymous link', function () {
    $link = Link::factory()->create([
        'short_code' => 'anon',
        'user_id' => null,
        'original_url' => 'https://old.com',
    ]);

    patchJson("/api/shorten/{$link->short_code}", ['url' => 'https://new.com'])
        ->assertOk();

    expect($link->fresh()->original_url)->toBe('https://new.com');
});

it('allows owner to update their own link', function () {
    $owner = User::factory()->create();
    $link = Link::factory()->forUser($owner)->create([
        'short_code' => 'mine',
        'original_url' => 'https://old.com',
    ]);

    actingAs($owner)
        ->patchJson("/api/shorten/{$link->short_code}", ['url' => 'https://updated.com'])
        ->assertOk();

    expect($link->fresh()->original_url)->toBe('https://updated.com');
});

it('invalidates cache after update via observer', function () {
    $link = Link::factory()->create([
        'short_code' => 'cache1',
        'original_url' => 'https://old.com',
        'expires_at' => null,
    ]);

    expect(Cache::get('link_cache1'))->toBe('https://old.com');

    patchJson("/api/shorten/{$link->short_code}", ['url' => 'https://new.com'])->assertOk();

    expect(Cache::has('link_cache1'))->toBeFalse();
});
