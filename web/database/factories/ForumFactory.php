<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Forum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Forum>
 */
class ForumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(2, true));

        return [
            'key' => 'f'.fake()->unique()->numberBetween(10, 999),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(8),
            'icon' => fake()->randomElement(['bi-chat-heart', 'bi-bank', 'bi-heart-pulse', 'bi-people', 'bi-graph-up-arrow']),
            'position' => fake()->numberBetween(0, 20),
            'topics_count' => 0,
            'posts_count' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
