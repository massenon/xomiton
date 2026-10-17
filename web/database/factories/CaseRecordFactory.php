<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgeRange;
use App\Enums\AlertSource;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\Sex;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\Population;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseRecord>
 */
class CaseRecordFactory extends Factory
{
    protected $model = CaseRecord::class;

    /** @var list<string> orientations du prototype (cases.json `referral`) */
    private const REFERRALS = ['Point focal', 'Juriste', 'Psychologue', 'Gynécologue', 'Hébergement'];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'kind' => fake()->randomElement(CaseKind::cases()),
            // Le code suit la nature tirée au sort : évalué après les autres attributs (RG-06).
            'code' => fn (array $attributes): string => CaseRecord::nextCode(
                $attributes['kind'] instanceof CaseKind ? $attributes['kind'] : CaseKind::from($attributes['kind']),
            ),
            'occurred_on' => fake()->dateTimeBetween('-11 months', 'now')->format('Y-m-d'),
            'zone_id' => Zone::factory(),
            'commune_id' => fn (array $attributes) => Commune::factory()->for(Zone::find($attributes['zone_id']) ?? Zone::factory()->create()),
            'population_id' => Population::factory(),
            'sex' => fake()->randomElement(Sex::cases()),
            'age_range' => fake()->randomElement(AgeRange::cases()),
            'violence_type_id' => ViolenceType::factory(),
            'status' => CaseStatus::Open,
            'collected_by' => User::factory(),
            'referral' => fake()->randomElement(self::REFERRALS),
            'notes' => 'Prise en charge initiale effectuée.',
            'source' => AlertSource::Web,
            'client_uuid' => null,
            'device_id' => null,
            'closed_at' => null,
            'archived_at' => null,
            'anonymized_at' => null,
        ];
    }

    public function vbg(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => CaseKind::Vbg]);
    }

    public function vdh(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => CaseKind::Vdh]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CaseStatus::InProgress]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CaseStatus::Closed,
            'closed_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CaseStatus::Archived,
            'closed_at' => now()->subMonths(2),
            'archived_at' => now()->subDay(),
        ]);
    }

    /** Dossier saisi hors connexion puis synchronisé (clé d'idempotence). */
    public function synced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => AlertSource::SyncOffline,
            'client_uuid' => fake()->uuid(),
        ]);
    }

    public function collectedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['collected_by' => $user->getKey()]);
    }
}
