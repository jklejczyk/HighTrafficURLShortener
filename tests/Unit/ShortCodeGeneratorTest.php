<?php

use App\Models\Link;
use App\Services\ShortCodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates a 7-character code', function () {
    $code = (new ShortCodeGeneratorService)->generate();

    expect($code)->toHaveLength(7);
});

it('generates only base62 characters', function () {
    $code = (new ShortCodeGeneratorService)->generate();

    expect($code)->toMatch('/^[a-zA-Z0-9]{7}$/');
});

it('generates unique codes across 100 iterations', function () {
    $generator = new ShortCodeGeneratorService;
    $codes = [];

    for ($i = 0; $i < 100; $i++) {
        $code = $generator->generate();
        Link::factory()->create(['short_code' => $code]);
        $codes[] = $code;
    }

    expect($codes)->toHaveCount(100)
        ->and(array_unique($codes))->toHaveCount(100);
});
