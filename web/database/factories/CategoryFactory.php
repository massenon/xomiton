<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = Str::ucfirst(fake('fr_FR')->unique()->words(2, true));

        return [
            'kind' => Category::KIND_THEMATIQUE,
            'slug' => Str::slug($label),
            'label' => $label,
            'icon' => 'bi-'.fake()->randomElement(['book', 'bank', 'heart-pulse', 'droplet-half', 'person-check']),
            'color' => fake()->randomElement(['danger', 'primary', 'info', 'success', 'warning', 'secondary']),
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function ofKind(string $kind): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
