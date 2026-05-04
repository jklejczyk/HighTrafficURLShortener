<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_code' => fake()->unique()->regexify('[a-zA-Z0-9]{7}'),
            'original_url' => fake()->url(),
            'user_id' => null,
            'click_count' => fake()->numberBetween(0, 10000),
            'expires_at' => null,
        ];
    }

    public function forUser(User|int|null $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user instanceof User ? $user->id : ($user ?? User::factory()),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 hour'),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'expires_at' => fake()->dateTimeBetween('+1 hour', '+7 days'),
        ]);
    }

    public function withClicks(int $count): static
    {
        return $this->state(fn () => ['click_count' => $count]);
    }

    public function popular(): static
    {
        return $this->state(fn () => [
            'click_count' => fake()->numberBetween(10_000, 1_000_000),
        ]);
    }
}
