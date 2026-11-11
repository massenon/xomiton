<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Population;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Population>
 */
class PopulationFactory extends Factory
{
    protected $model = Population::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = Str::ucfirst(fake('fr_FR')->unique()->words(2, true));

        return [
            'slug' => Str::slug($label),
            'label' => $label,
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
