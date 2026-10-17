<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ValidationRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ValidationRule>
 */
class ValidationRuleFactory extends Factory
{
    protected $model = ValidationRule::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $label = Str::ucfirst(fake('fr_FR')->unique()->words(3, true));

        return [
            'key' => Str::slug($label, '_'),
            'label' => $label,
            'description' => fake('fr_FR')->sentence(8),
            'is_active' => true,
            'is_locked' => false,
            'params' => null,
            'valid_example' => fake('fr_FR')->word(),
            'invalid_example' => fake('fr_FR')->word(),
            'position' => fake()->numberBetween(1, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /** Règle verrouillée (`required`) : toujours active, non désactivable. */
    public function locked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => ValidationRule::KEY_REQUIRED,
            'is_active' => true,
            'is_locked' => true,
        ]);
    }

    /** Règle de plausibilité avec les bornes par défaut du prototype. */
    public function plausibility(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => ValidationRule::KEY_PLAUSIBILITY,
            'params' => [
                'age_min' => 10,
                'age_max' => 99,
                'max_past_years' => 5,
                'lat' => [6.2, 12.5],
                'lng' => [0.7, 3.9],
            ],
        ]);
    }

    /** Détection de doublons sur les champs par défaut du prototype. */
    public function duplicates(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => ValidationRule::KEY_DUPLICATES,
            'params' => ['fields' => ['date', 'commune', 'population', 'age']],
        ]);
    }
}
