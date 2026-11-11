<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'category_id' => null,
            'question' => Str::ucfirst(rtrim(fake('fr_FR')->unique()->sentence(8), '.')).' ?',
            'answer' => fake('fr_FR')->paragraph(),
            'position' => fake()->numberBetween(1, 50),
            'is_published' => true,
            'show_on_home' => false,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => false]);
    }

    public function onHome(): static
    {
        return $this->state(fn (array $attributes): array => ['show_on_home' => true]);
    }
}
