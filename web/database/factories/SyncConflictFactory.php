<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConflictStatus;
use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<SyncConflict>
 */
class SyncConflictFactory extends Factory
{
    protected $model = SyncConflict::class;

    /**
     * Conflits du prototype (`sync_conflicts.json` : champ, version serveur, version appareil).
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const SAMPLES = [
        ['status', 'ouvert', 'clos'],
        ['referral', 'Psychologue', 'Juriste'],
        ['notes', 'Premier contact effectué.', 'Premier contact effectué ; rappel prévu jeudi.'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$field, $server, $device] = fake()->randomElement(self::SAMPLES);

        return [
            'device_id' => SyncDevice::factory(),
            'subject_type' => (new CaseRecord)->getMorphClass(),
            'subject_id' => fake()->numberBetween(1, 500),
            'field' => $field,
            'server_value' => $server,
            'device_value' => $device,
            'status' => ConflictStatus::Open,
            'resolution' => null,
            'resolved_value' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }

    public function forDevice(SyncDevice $device): static
    {
        return $this->state(fn (array $attributes): array => ['device_id' => $device->getKey()]);
    }

    public function on(Model $subject, string $field): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'field' => $field,
        ]);
    }

    /**
     * Conflit déjà tranché (version serveur par défaut).
     */
    public function resolved(string $resolution = SyncConflict::RESOLUTION_SERVER): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConflictStatus::Resolved,
            'resolution' => $resolution,
            'resolved_value' => $resolution === SyncConflict::RESOLUTION_DEVICE ? $attributes['device_value'] : $attributes['server_value'],
            'resolved_by' => User::factory(),
            'resolved_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    /**
     * Conflit dont l'appareil d'origine a été supprimé.
     */
    public function orphan(): static
    {
        return $this->state(fn (array $attributes): array => ['device_id' => null]);
    }
}
