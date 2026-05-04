<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->count(5)->create();

        $userIds = User::query()->pluck('id')->all();

        Link::factory()->count(100)->create([
            'user_id'    => fn () => fake()->boolean(30) ? fake()->randomElement($userIds) : null,
            'expires_at' => fn () => fake()->boolean(20) ? fake()->dateTimeBetween('-7 days', '+30 days') : null,
        ]);
    }
}
