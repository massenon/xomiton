<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Commune;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Commune>
 */
class CommuneFactory extends Factory
{
    protected $model = Commune::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake('fr_FR')->unique()->city();

        return [
            'zone_id' => Zone::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => fake()->numberBetween(1, 99),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
