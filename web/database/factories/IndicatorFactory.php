<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IndicatorFrequency;
use App\Models\Indicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Indicator>
 */
class IndicatorFactory extends Factory
{
    protected $model = Indicator::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isPercent = fake()->boolean(40);

        return [
            'code' => 'IND-'.str_pad((string) fake()->unique()->numberBetween(20, 999), 2, '0', STR_PAD_LEFT),
            'label' => ucfirst(fake('fr_FR')->words(4, true)),
            'definition' => fake('fr_FR')->sentence(10),
            'formula' => 'Σ '.fake('fr_FR')->words(3, true),
            'source' => fake()->randomElement(['Registres', 'Alertes', 'Réseau social', 'Newsletter', 'Utilisateurs']),
            'frequency' => fake()->randomElement(IndicatorFrequency::cases()),
            'target' => $isPercent ? fake()->numberBetween(50, 95) : fake()->numberBetween(5, 100),
            'unit' => $isPercent ? Indicator::UNIT_PERCENT : Indicator::UNIT_COUNT,
            'calc_key' => fake()->unique()->slug(2),
            'better' => Indicator::BETTER_UP,
            'is_builtin' => false,
            'is_active' => true,
            'position' => fake()->numberBetween(20, 99),
        ];
    }

    /** Indicateur intégré du prototype, calculé automatiquement. */
    public function builtin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_builtin' => true,
        ]);
    }

    /** Indicateur saisi à la main (sans clé de calcul). */
    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'calc_key' => null,
            'is_builtin' => false,
        ]);
    }

    public function percent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit' => Indicator::UNIT_PERCENT,
            'target' => fake()->numberBetween(50, 95),
        ]);
    }

    /** Indicateur dont la baisse est souhaitable (ex. SOS reçus). */
    public function betterDown(): static
    {
        return $this->state(fn (array $attributes): array => [
            'better' => Indicator::BETTER_DOWN,
        ]);
    }

    public function withoutTarget(): static
    {
        return $this->state(fn (array $attributes): array => [
            'target' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function frequency(IndicatorFrequency $frequency): static
    {
        return $this->state(fn (array $attributes): array => [
            'frequency' => $frequency,
        ]);
    }
}
