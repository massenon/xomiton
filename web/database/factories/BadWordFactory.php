<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BadWordSeverity;
use App\Models\BadWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadWord>
 */
class BadWordFactory extends Factory
{
    protected $model = BadWord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'word' => fake('fr_FR')->unique()->word().fake()->numberBetween(1, 999),
            'severity' => BadWordSeverity::Flag,
            'is_active' => true,
        ];
    }

    public function blocking(): static
    {
        return $this->state(fn (array $attributes): array => ['severity' => BadWordSeverity::Block]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
