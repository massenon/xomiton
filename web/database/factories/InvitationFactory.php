<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'status' => InvitationStatus::Pending,
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    public function refused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Refused,
            'responded_at' => now(),
        ]);
    }
}
