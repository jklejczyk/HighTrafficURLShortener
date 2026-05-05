<?php

namespace App\Services;

use App\Models\Link;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class LinkCacheService
{
    private const string KEY_PREFIX = 'link_';

    private const string NOT_FOUND_SENTINEL = 'NOT_FOUND';

    private const int POSITIVE_TTL_HOURS = 24;

    private const int NEGATIVE_TTL_MINUTES = 5;

    public function get(string $code): ?string
    {
        $cached = Cache::get($this->key($code));

        if ($cached === self::NOT_FOUND_SENTINEL) {
            return null;
        }
        if ($cached !== null) {
            return $cached;
        }

        $link = Link::where('short_code', $code)->first();

        if (! $link || $link->expires_at?->isPast()) {
            Cache::put($this->key($code), self::NOT_FOUND_SENTINEL, now()->addMinutes(self::NEGATIVE_TTL_MINUTES));

            return null;
        }

        Cache::put($this->key($code), $link->original_url, now()->addSeconds($this->calculateTtlInSeconds($link->expires_at)));

        return $link->original_url;
    }

    public function store(Link $link): void
    {
        if ($link->expires_at?->isPast()) {
            return;
        }

        Cache::put($this->key($link->short_code), $link->original_url, $this->calculateTtlInSeconds($link->expires_at));
    }

    public function forget(string $code): void
    {
        Cache::forget($this->key($code));
    }

    private function key(string $code): string
    {
        return self::KEY_PREFIX.$code;
    }

    private function calculateTtlInSeconds(?CarbonInterface $expiresAt): int
    {
        if (! $expiresAt) {
            return self::POSITIVE_TTL_HOURS * 3600;
        }

        $secondsLeft = (int) now()->diffInSeconds($expiresAt, false);

        if ($secondsLeft <= 0) {
            throw new \LogicException('TTL must be a positive integer, got '.$expiresAt->toIso8601String());
        }

        // choose lower value
        // if $secondsLeft > 24h, choose POSITIVE_TTL_HOURS
        return min($secondsLeft, self::POSITIVE_TTL_HOURS * 3600);
    }
}
