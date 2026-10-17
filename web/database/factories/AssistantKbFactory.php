<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssistantKb;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistantKb>
 */
class AssistantKbFactory extends Factory
{
    protected $model = AssistantKb::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question' => Str::ucfirst(rtrim(fake('fr_FR')->unique()->sentence(6), '.')).' ?',
            'answer' => fake('fr_FR')->paragraph(),
            'keywords' => fake('fr_FR')->words(3),
            'is_active' => true,
            'hits_count' => 0,
        ];
    }

    /** @param  list<string>  $keywords */
    public function withKeywords(array $keywords): static
    {
        return $this->state(fn (array $attributes): array => ['keywords' => $keywords]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
