<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(10),
            'media_path' => null,
            'client_uuid' => (string) Str::uuid(),
            'delivered_at' => null,
            'read_at' => null,
            'deleted_for_everyone_at' => null,
        ];
    }

    public function withMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_path' => 'messages/'.fake()->uuid().'.jpg',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivered_at' => now(),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivered_at' => now()->subMinute(),
            'read_at' => now(),
        ]);
    }

    public function deletedForEveryone(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_for_everyone_at' => now(),
        ]);
    }

    /**
     * Message dans une conversation existante, envoyé par le ou la membre donné·e.
     */
    public function in(Conversation $conversation, ?User $sender = null): static
    {
        return $this->state(fn (array $attributes) => [
            'conversation_id' => $conversation->id,
            'user_id' => $sender?->id ?? $attributes['user_id'],
        ]);
    }
}
