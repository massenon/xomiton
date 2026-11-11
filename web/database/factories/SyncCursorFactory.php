<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SyncCursor;
use App\Models\SyncDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncCursor>
 */
class SyncCursorFactory extends Factory
{
    protected $model = SyncCursor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => SyncDevice::factory(),
            'collection' => fake()->randomElement(SyncCursor::COLLECTIONS),
            'cursor' => fake()->numberBetween(1, 10_000),
            'pulled_at' => fake()->dateTimeBetween('-2 days'),
        ];
    }

    public function forDevice(SyncDevice $device): static
    {
        return $this->state(fn (array $attributes): array => ['device_id' => $device->getKey()]);
    }

    public function collection(string $collection): static
    {
        return $this->state(fn (array $attributes): array => ['collection' => $collection]);
    }

    /**
     * Curseur vierge : l'appareil n'a encore rien tiré.
     */
    public function untouched(): static
    {
        return $this->state(fn (array $attributes): array => ['cursor' => 0, 'pulled_at' => null]);
    }
}
