<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DirectoryRole;
use App\Models\Need;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Need>
 */
class NeedFactory extends Factory
{
    protected $model = Need::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = Str::ucfirst(fake('fr_FR')->unique()->words(2, true));

        return [
            'slug' => Str::slug($label, '_'),
            'label' => $label,
            'routed_role' => null,
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function routedTo(DirectoryRole $role): static
    {
        return $this->state(fn (array $attributes): array => ['routed_role' => $role]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
