<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistantConversation>
 */
class AssistantConversationFactory extends Factory
{
    protected $model = AssistantConversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_key' => null,
            'is_flagged' => false,
            'flag_reason' => null,
            'escalated_at' => null,
        ];
    }

    /** Conversation d'un·e visiteur·se non connecté·e, identifiée par sa session. */
    public function anonymous(?string $sessionKey = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'session_key' => $sessionKey ?? Str::random(40),
        ]);
    }

    public function flagged(string $reason = 'Mots bloquants détectés'): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_flagged' => true,
            'flag_reason' => $reason,
        ]);
    }

    public function escalated(): static
    {
        return $this->flagged('Demande de contact humain')->state(fn (array $attributes): array => [
            'escalated_at' => now(),
        ]);
    }
}
