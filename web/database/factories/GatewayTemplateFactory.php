<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GatewayTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GatewayTemplate>
 */
class GatewayTemplateFactory extends Factory
{
    protected $model = GatewayTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement([
            GatewayTemplate::CATEGORY_PREVENTION,
            GatewayTemplate::CATEGORY_DEPISTAGE,
            GatewayTemplate::CATEGORY_SOS,
        ]);

        return [
            'title' => ucfirst($category).' – '.fake()->words(2, true),
            'body' => fake()->realTextBetween(GatewayTemplate::BODY_MIN_LENGTH, 220),
            'category' => $category,
            'is_active' => true,
            'position' => fake()->numberBetween(1, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function category(string $category): static
    {
        return $this->state(fn (array $attributes): array => ['category' => $category]);
    }
}
