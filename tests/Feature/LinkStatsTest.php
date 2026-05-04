<?php

use App\Models\Link;

use function Pest\Laravel\getJson;

it('returns metadata for an existing link', function () {
    $link = Link::factory()->create([
        'short_code'   => 'abc1234',
        'original_url' => 'https://google.com',
        'click_count'  => 42,
    ]);

    getJson("/api/stats/{$link->short_code}")
        ->assertOk()
        ->assertJsonStructure([
            'short_code',
            'original_url',
            'click_count',
            'created_at',
            'expires_at',
        ])
        ->assertJson([
            'short_code'   => 'abc1234',
            'original_url' => 'https://google.com',
            'click_count'  => 42,
        ]);
});

it('returns 404 for an unknown code', function () {
    getJson('/api/stats/missing')->assertNotFound();
});

it('returns expires_at when the link has expiry set', function () {
    $link = Link::factory()->expiringSoon()->create(['short_code' => 'soon']);

    $response = getJson("/api/stats/{$link->short_code}")->assertOk();

    expect($response->json('expires_at'))->not->toBeNull();
});

it('returns null expires_at when the link has no expiry', function () {
    $link = Link::factory()->create([
        'short_code' => 'noexp',
        'expires_at' => null,
    ]);

    getJson("/api/stats/{$link->short_code}")
        ->assertOk()
        ->assertJson(['expires_at' => null]);
});
