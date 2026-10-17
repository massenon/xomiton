<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Law;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Law>
 */
class LawFactory extends Factory
{
    protected $model = Law::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1990, 2030).'-'.fake()->numberBetween(1, 99);

        return [
            'slug' => 'loi-'.$number,
            'title' => 'Loi n°'.$number.' du '.fake('fr_FR')->date('j F Y'),
            'subtitle' => Str::ucfirst(rtrim(fake('fr_FR')->sentence(5), '.')),
            'summary' => fake('fr_FR')->paragraph(),
            'category' => fake()->randomElement(['Violences', 'Famille', 'Pénal', 'Santé', 'International', 'Constitution']),
            'official_url' => null,
            'position' => fake()->numberBetween(1, 20),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => false]);
    }
}
