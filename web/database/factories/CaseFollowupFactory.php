<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FollowupOutcome;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CaseFollowup>
 */
class CaseFollowupFactory extends Factory
{
    protected $model = CaseFollowup::class;

    /** @var list<string> étapes du prototype (suivi.json `step`) */
    private const STEPS = ['Accueil', 'Écoute', 'Appui juridique', 'Appui psychologique', 'Orientation médicale', 'Suivi à 30 jours'];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = Carbon::instance(fake()->dateTimeBetween('-6 months', 'now'))->startOfDay();

        return [
            'case_id' => CaseRecord::factory(),
            'date' => $date->toDateString(),
            'step' => fake()->randomElement(self::STEPS),
            'by_user_id' => User::factory(),
            'outcome' => fake()->randomElement(FollowupOutcome::cases()),
            // Le prototype planifie systématiquement la suite à J+14.
            'next_on' => $date->copy()->addDays(14)->toDateString(),
            'comment' => fake('fr_FR')->optional(0.5)->sentence(12),
            'client_uuid' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes): array => ['outcome' => FollowupOutcome::Planned]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes): array => ['outcome' => FollowupOutcome::Done]);
    }

    public function postponed(): static
    {
        return $this->state(fn (array $attributes): array => ['outcome' => FollowupOutcome::Postponed]);
    }

    /** Étape en attente dont l'échéance est passée (rappel à envoyer). */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'outcome' => FollowupOutcome::Planned,
            'date' => now()->subDays(30)->toDateString(),
            'next_on' => now()->subDays(16)->toDateString(),
        ]);
    }

    public function forCase(CaseRecord $case): static
    {
        return $this->state(fn (array $attributes): array => ['case_id' => $case->getKey()]);
    }
}
