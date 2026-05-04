<?php

namespace App\Services;

use App\Models\Link;

class ShortCodeGenerator
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const LENGTH = 7;

    public function generate(): string
    {
        do {
            $code = $this->random();
        } while (Link::where('short_code', $code)->exists());

        return $code;
    }

    private function random(): string
    {
        $alphabet = self::ALPHABET;
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, 61)];
        }

        return $code;
    }
}
