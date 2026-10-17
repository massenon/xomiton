<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModerationReport;
use App\Models\User;
use App\Models\UserWarning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWarning>
 */
class UserWarningFactory extends Factory
{
    protected $model = UserWarning::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issued_by' => User::factory(),
            'reason' => fake()->randomElement([
                'Propos insultants dans un commentaire.',
                'Partage de données personnelles d’une autre membre.',
                'Messages privés non sollicités répétés.',
                'Contenu contraire à la charte de la communauté.',
            ]),
            'moderation_report_id' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }

    /** Avertissement issu d'un signalement traité, adressé à la personne signalée. */
    public function fromReport(?ModerationReport $report = null): static
    {
        return $this->state(function (array $attributes) use ($report): array {
            $report ??= ModerationReport::factory()->handled(ModerationReport::ACTION_WARN)->create();

            return [
                'moderation_report_id' => $report->id,
                'user_id' => $report->reported_user_id ?? $attributes['user_id'],
                'issued_by' => $report->handled_by,
                'reason' => $report->reason,
            ];
        });
    }

    /** Avertissement émis par le système (compte émetteur absent). */
    public function automatic(): static
    {
        return $this->state(fn (array $attributes): array => [
            'issued_by' => null,
        ]);
    }

    public function daysAgo(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => now()->subDays($days),
        ]);
    }
}
