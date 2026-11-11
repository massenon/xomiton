<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBlock>
 */
class UserBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'blocked_user_id' => User::factory(),
            'reason' => fake()->optional(0.5)->randomElement(['Harcèlement', 'Messages déplacés', 'Spam', 'Autre']),
        ];
    }
}
