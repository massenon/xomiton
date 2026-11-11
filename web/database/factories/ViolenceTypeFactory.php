<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ViolenceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ViolenceType>
 */
class ViolenceTypeFactory extends Factory
{
    protected $model = ViolenceType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = 'Violence '.fake('fr_FR')->unique()->word();

        return [
            'slug' => Str::slug($label),
            'label' => Str::ucfirst($label),
            'icon' => 'bi-'.fake()->randomElement(['shield', 'hand-index-thumb', 'chat-square-heart', 'cash-coin', 'phone']),
            'description' => fake('fr_FR')->sentence(8),
            'position' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
