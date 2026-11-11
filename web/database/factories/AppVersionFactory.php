<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppVersion>
 */
class AppVersionFactory extends Factory
{
    protected $model = AppVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deployedAt = fake()->dateTimeBetween('-1 year', '-1 day');

        return [
            'version' => fake()->unique()->regexify('[1-9]\.[0-9]{1,2}\.[0-9]{1,2}'),
            'kind' => fake()->randomElement(AppVersion::KINDS),
            'notes' => fake('fr_FR')->sentence(12),
            'status' => AppVersion::STATUS_DEPLOYED,
            'scheduled_for' => null,
            'deployed_at' => $deployedAt,
            'created_by' => User::factory(),
        ];
    }

    public function version(string $version): static
    {
        return $this->state(fn (array $attributes): array => ['version' => $version]);
    }

    public function ofKind(string $kind): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind]);
    }

    /**
     * Version planifiée pour une date à venir, pas encore déployée.
     */
    public function planned(?string $scheduledFor = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppVersion::STATUS_PLANNED,
            'scheduled_for' => $scheduledFor ?? fake()->dateTimeBetween('+1 week', '+3 months'),
            'deployed_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppVersion::STATUS_CANCELLED,
            'deployed_at' => null,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => ['created_by' => null]);
    }
}
