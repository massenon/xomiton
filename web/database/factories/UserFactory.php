<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pseudo' => Str::lower(fake()->unique()->userName()).fake()->numberBetween(1, 99),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Pending,
            'email_verified_at' => null,
        ]);
    }

    public function suspended(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason ?? fake()->sentence(6),
        ]);
    }

    /**
     * Compte complet : profil et réglages créés avec l'utilisateur·rice.
     */
    public function withProfile(array $profile = [], array $settings = []): static
    {
        return $this->afterCreating(function (User $user) use ($profile, $settings): void {
            Profile::factory()->for($user)->create($profile);
            UserSetting::factory()->for($user)->create($settings);
        });
    }

    /**
     * Pair éducateur·rice : profil de type « pair ».
     */
    public function peer(): static
    {
        return $this->withProfile(['member_type' => 'pair']);
    }
}
