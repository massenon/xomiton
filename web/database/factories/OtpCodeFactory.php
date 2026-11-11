<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'purpose' => OtpPurpose::Login,
            'code_hash' => OtpCode::hash((string) fake()->numberBetween(100000, 999999)),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('xomiton.otp.ttl_minutes', 10)),
            'consumed_at' => null,
            'ip' => fake()->ipv4(),
        ];
    }

    /**
     * Code dont la valeur en clair est connue du test.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code_hash' => OtpCode::hash($code),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consumed_at' => now(),
        ]);
    }

    public function forPurpose(OtpPurpose $purpose): static
    {
        return $this->state(fn (array $attributes) => [
            'purpose' => $purpose,
        ]);
    }
}
