<?php

use App\Models\Link;

use function Pest\Laravel\get;

it('redirects to original url with status 301', function () {
    $link = Link::factory()->create([
        'short_code' => 'abc1234',
        'original_url' => 'https://google.com',
    ]);

    get("/{$link->short_code}")
        ->assertStatus(301)
        ->assertRedirect('https://google.com');
});

it('returns 404 for an unknown code', function () {
    get('/unknown')->assertNotFound();
});

it('returns 410 for an expired link', function () {
    $link = Link::factory()->expired()->create(['short_code' => 'expired']);

    get("/{$link->short_code}")->assertStatus(410);
});

it('does not return 410 for a link expiring in the future', function () {
    $link = Link::factory()->expiringSoon()->create([
        'short_code' => 'future',
        'original_url' => 'https://example.com',
    ]);

    get("/{$link->short_code}")->assertStatus(301);
});

it('increments click_count after a successful redirect', function () {
    $link = Link::factory()->create([
        'short_code' => 'click',
        'click_count' => 5,
    ]);

    get("/{$link->short_code}");

    expect($link->fresh()->click_count)->toBe(6);
});

it('does not increment click_count for an expired link', function () {
    $link = Link::factory()->expired()->create([
        'short_code' => 'noinc',
        'click_count' => 5,
    ]);

    get("/{$link->short_code}");

    expect($link->fresh()->click_count)->toBe(5);
});
