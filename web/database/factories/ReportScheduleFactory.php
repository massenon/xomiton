<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ReportKind::Monthly,
            'frequency' => ReportSchedule::FREQUENCY_MONTHLY,
            'day_of_period' => fake()->numberBetween(1, ReportSchedule::MAX_DAY_OF_PERIOD),
            'formats' => [ReportFormat::Pdf->value],
            'recipients' => [fake()->unique()->safeEmail(), fake()->unique()->safeEmail()],
            'dimensions' => ['departement', 'population', 'sexe', 'age'],
            'is_active' => true,
            'last_run_at' => null,
            'next_run_at' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * L'échéance par défaut découle de la fréquence et du jour d'envoi (`computeNextRunAt`),
     * sauf valeur explicite (`due()`).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ReportSchedule $schedule): void {
            if ($schedule->next_run_at === null) {
                $schedule->next_run_at = $schedule->computeNextRunAt();
            }
        });
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ReportKind::Quarterly,
            'frequency' => ReportSchedule::FREQUENCY_QUARTERLY,
        ]);
    }

    public function semiannual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ReportKind::Semiannual,
            'frequency' => ReportSchedule::FREQUENCY_SEMIANNUAL,
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ReportKind::Annual,
            'frequency' => ReportSchedule::FREQUENCY_ANNUAL,
        ]);
    }

    /**
     * @param  list<ReportFormat>  $formats
     */
    public function formats(array $formats): static
    {
        return $this->state(fn (array $attributes): array => [
            'formats' => array_map(static fn (ReportFormat $format): string => $format->value, $formats),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** Échéance déjà atteinte : à exécuter au prochain passage de la tâche planifiée. */
    public function due(): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_run_at' => now()->subHour(),
        ]);
    }

    public function alreadyRun(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_run_at' => now()->subMonth(),
        ]);
    }
}
