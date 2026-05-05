<?php

use App\Models\Link;
use App\Models\User;
use App\Policies\LinkPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new LinkPolicy();
});

it('allows update of anonymous link by guest', function () {
    $link = Link::factory()->create(['user_id' => null]);

    expect($this->policy->update(null, $link))->toBeTrue();
});

it('allows update of anonymous link by any user', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => null]);

    expect($this->policy->update($user, $link))->toBeTrue();
});

it('allows update of own link by owner', function () {
    $user = User::factory()->create();
    $link = Link::factory()->forUser($user)->create();

    expect($this->policy->update($user, $link))->toBeTrue();
});

it('denies update of others link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->forUser($owner)->create();

    expect($this->policy->update($other, $link))->toBeFalse();
});

it('denies update of owned link by guest', function () {
    $link = Link::factory()->forUser()->create();

    expect($this->policy->update(null, $link))->toBeFalse();
});

it('uses same rules for delete as update', function () {
    $link = Link::factory()->forUser()->create();

    expect($this->policy->delete(null, $link))->toBe($this->policy->update(null, $link));
});