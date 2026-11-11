<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ConversationKind::Private,
            'title' => null,
            'group_id' => null,
            'created_by' => User::factory(),
            'last_message_at' => null,
            'last_message_preview' => null,
        ];
    }

    /**
     * Conversation de groupe titrée, éventuellement rattachée à un groupe de la communauté.
     */
    public function group(?Group $group = null): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ConversationKind::Group,
            'title' => $group?->name ?? fake()->words(3, true),
            'group_id' => $group?->id,
        ]);
    }

    /**
     * Ajoute les membres donnés comme participant·es (le ou la créateur·rice en admin).
     *
     * @param  list<User>  $users
     */
    public function withParticipants(array $users): static
    {
        return $this->afterCreating(function (Conversation $conversation) use ($users): void {
            foreach ($users as $user) {
                ConversationParticipant::factory()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => $user->id === $conversation->created_by
                        ? ConversationParticipant::ROLE_ADMIN
                        : ConversationParticipant::ROLE_MEMBER,
                ]);
            }
        });
    }
}
