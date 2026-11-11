<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsentKind;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => null,
            'kind' => fake()->randomElement(ConsentKind::cases()),
            'version' => '1.0',
            'granted' => true,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Consentement d'un·e visiteur·se (newsletter) sans compte.
     */
    public function visitor(?string $email = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'email' => $email ?? fake()->safeEmail(),
            'kind' => ConsentKind::Newsletter,
        ]);
    }

    public function ofKind(ConsentKind $kind): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => $kind,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'granted' => false,
        ]);
    }
}
