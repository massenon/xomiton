<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationParticipant>
 */
class ConversationParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'role' => ConversationParticipant::ROLE_MEMBER,
            'last_read_message_id' => null,
            'muted_until' => null,
            'left_at' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ConversationParticipant::ROLE_ADMIN,
        ]);
    }

    public function muted(): static
    {
        return $this->state(fn (array $attributes) => [
            'muted_until' => now()->addDay(),
        ]);
    }

    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'left_at' => now(),
        ]);
    }
}
