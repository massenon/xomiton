<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SyncDevice>
 */
class SyncDeviceFactory extends Factory
{
    protected $model = SyncDevice::class;

    /**
     * Plateformes du prototype (`sync_devices.json` : « Android 13 · Redmi 10 »…).
     *
     * @var list<string>
     */
    private const PLATFORMS = [
        'Android 13 · Redmi 10',
        'Android 12 · Tecno Spark',
        'Android 14 · Samsung A15',
        'Android 11 · Infinix Hot 10',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'device_uuid' => (string) Str::uuid(),
            'label' => fake('fr_FR')->firstName().' '.mb_substr(fake('fr_FR')->lastName(), 0, 1).'. — '.fake('fr_FR')->city(),
            'platform' => fake()->randomElement(self::PLATFORMS),
            'app_version' => '1.'.fake()->numberBetween(0, 4).'.'.fake()->numberBetween(0, 9),
            'last_seen_at' => fake()->dateTimeBetween('-6 hours'),
            'last_sync_at' => fake()->dateTimeBetween('-6 hours'),
            'pending_count' => 0,
            'conflicts_count' => 0,
            'is_revoked' => false,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_revoked' => true,
        ]);
    }

    /**
     * Appareil silencieux depuis plus longtemps que le seuil `xomiton.sync.device_stale_hours`.
     */
    public function stale(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => now()->subHours((int) config('xomiton.sync.device_stale_hours', 24) + 6),
            'last_sync_at' => now()->subHours((int) config('xomiton.sync.device_stale_hours', 24) + 6),
        ]);
    }

    public function withPending(int $pending = 7, int $conflicts = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'pending_count' => $pending,
            'conflicts_count' => $conflicts,
        ]);
    }
}
