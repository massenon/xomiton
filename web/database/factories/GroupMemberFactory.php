<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMember>
 */
class GroupMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'role' => GroupRole::Member,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => GroupRole::Admin,
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => GroupRole::Moderator,
        ]);
    }

    /**
     * Demande d'adhésion à un groupe privé, non encore validée.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Requested,
            'joined_at' => null,
        ]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Banned,
        ]);
    }
}
