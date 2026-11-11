<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicatorValue>
 */
class IndicatorValueFactory extends Factory
{
    protected $model = IndicatorValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::now()->subMonths(fake()->numberBetween(1, 12))->startOfMonth();

        return [
            'indicator_id' => Indicator::factory(),
            'period_start' => $start->toDateString(),
            'period_end' => $start->endOfMonth()->toDateString(),
            'zone_id' => null,
            'value' => fake()->randomFloat(2, 0, 100),
            'is_manual' => false,
            'computed_at' => $start->endOfMonth()->addDay()->setTime(2, 0),
        ];
    }

    /** Valeur saisie à la main. */
    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_manual' => true,
        ]);
    }

    public function forZone(?Zone $zone = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'zone_id' => $zone?->id ?? Zone::factory(),
        ]);
    }

    /** Période d'un mois civil donné (`2026-03` → 1er au 31 mars). */
    public function forMonth(int $year, int $month): static
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return $this->forPeriod($start, $start->endOfMonth());
    }

    public function forPeriod(CarbonInterface $start, CarbonInterface $end): static
    {
        return $this->state(fn (array $attributes): array => [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ]);
    }

    public function value(float $value): static
    {
        return $this->state(fn (array $attributes): array => [
            'value' => $value,
        ]);
    }
}
