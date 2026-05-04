<?php

use App\Models\Link;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('creates a short link for a valid url', function () {
    postJson('/api/shorten', ['url' => 'https://google.com'])
        ->assertOk()
        ->assertJsonStructure(['short_url']);

    expect(Link::query()->count())->toBe(1)
        ->and(Link::query()->first()->original_url)->toBe('https://google.com');
});

it('returns short_url ending with the generated short_code', function () {
    $response = postJson('/api/shorten', ['url' => 'https://google.com']);

    $code = Link::query()->first()->short_code;
    expect($response->json('short_url'))->toEndWith("/{$code}");
});

it('rejects an invalid url', function () {
    postJson('/api/shorten', ['url' => 'not-a-url'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('rejects a missing url', function () {
    postJson('/api/shorten', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

it('rejects a user_id that does not exist in users table', function () {
    postJson('/api/shorten', [
        'url' => 'https://google.com',
        'user_id' => 999_999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);

    expect(Link::query()->count())->toBe(0);
});

it('creates link with null user_id for unauthenticated request', function () {
    postJson('/api/shorten', ['url' => 'https://google.com'])->assertOk();

    expect(Link::query()->first()->user_id)->toBeNull();
});

it('assigns the authenticated user as link owner', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/shorten', ['url' => 'https://google.com'])
        ->assertOk();

    expect(Link::query()->first()->user_id)->toBe($user->id);
});

it('ignores user_id from request body and uses authenticated user instead', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    actingAs($owner)
        ->postJson('/api/shorten', [
            'url' => 'https://google.com',
            'user_id' => $other->id,
        ])
        ->assertOk();

    expect(Link::query()->first()->user_id)->toBe($owner->id);
});
