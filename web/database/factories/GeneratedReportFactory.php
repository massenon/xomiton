<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use App\Models\GeneratedReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedReport>
 */
class GeneratedReportFactory extends Factory
{
    protected $model = GeneratedReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) CarbonImmutable::now()->subYear()->format('Y');

        return [
            'title' => 'Rapport annuel '.$year,
            'kind' => ReportKind::Annual,
            'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
            'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
            'dimensions' => ['departement', 'sexe', 'age'],
            'format' => ReportFormat::Preview,
            'filters' => null,
            'rows_count' => null,
            'file_path' => null,
            'file_size' => null,
            'status' => JobStatus::Pending,
            'error' => null,
            'recipients' => null,
            'generated_by' => User::factory(),
            'completed_at' => null,
            'expires_at' => null,
        ];
    }

    public function ofKind(ReportKind $kind): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => $kind,
            'title' => $kind->label(),
        ]);
    }

    public function format(ReportFormat $format): static
    {
        return $this->state(fn (array $attributes): array => [
            'format' => $format,
        ]);
    }

    /** Rapport terminé avec un fichier stocké, valable 30 jours. */
    public function done(?ReportFormat $format = null): static
    {
        return $this->state(function (array $attributes) use ($format): array {
            $format ??= ReportFormat::Pdf;

            return [
                'format' => $format,
                'status' => JobStatus::Done,
                'rows_count' => fake()->numberBetween(10, 300),
                'file_path' => $format->producesFile() ? 'reports/'.fake()->uuid().'.'.$format->extension() : null,
                'file_size' => $format->producesFile() ? fake()->numberBetween(20_000, 2_000_000) : null,
                'completed_at' => now(),
                'expires_at' => $format->producesFile() ? now()->addDays(30) : null,
            ];
        });
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JobStatus::Running,
        ]);
    }

    public function failed(?string $error = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JobStatus::Failed,
            'error' => $error ?? 'Délai de génération dépassé.',
            'completed_at' => now(),
        ]);
    }

    /** Fichier dont la conservation est échue. */
    public function expired(): static
    {
        return $this->done()->state(fn (array $attributes): array => [
            'completed_at' => now()->subDays(40),
            'expires_at' => now()->subDays(10),
        ]);
    }

    /**
     * @param  list<string>  $emails
     */
    public function withRecipients(array $emails): static
    {
        return $this->state(fn (array $attributes): array => [
            'recipients' => $emails,
        ]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'generated_by' => null,
        ]);
    }
}
