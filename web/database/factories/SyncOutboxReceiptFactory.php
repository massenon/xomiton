<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SyncDevice;
use App\Models\SyncOutboxReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<SyncOutboxReceipt>
 */
class SyncOutboxReceiptFactory extends Factory
{
    protected $model = SyncOutboxReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => SyncDevice::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'kind' => fake()->randomElement(SyncOutboxReceipt::KINDS),
            'subject_type' => null,
            'subject_id' => null,
            'result' => SyncOutboxReceipt::RESULT_CREATED,
            'payload_hash' => SyncOutboxReceipt::hashPayload(['seed' => fake()->uuid()]),
        ];
    }

    public function forDevice(SyncDevice $device): static
    {
        return $this->state(fn (array $attributes): array => ['device_id' => $device->getKey()]);
    }

    public function ofKind(string $kind): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind]);
    }

    public function withKey(string $idempotencyKey): static
    {
        return $this->state(fn (array $attributes): array => ['idempotency_key' => $idempotencyKey]);
    }

    /**
     * @param  array<mixed>|string  $payload
     */
    public function forPayload(array|string $payload): static
    {
        return $this->state(fn (array $attributes): array => ['payload_hash' => SyncOutboxReceipt::hashPayload($payload)]);
    }

    public function result(string $result): static
    {
        return $this->state(fn (array $attributes): array => ['result' => $result]);
    }

    public function created(Model $subject): static
    {
        return $this->state(fn (array $attributes): array => [
            'result' => SyncOutboxReceipt::RESULT_CREATED,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }
}
