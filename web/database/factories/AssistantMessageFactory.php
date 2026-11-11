<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantMessage>
 */
class AssistantMessageFactory extends Factory
{
    protected $model = AssistantMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'conversation_id' => AssistantConversation::factory(),
            'role' => AssistantMessage::ROLE_USER,
            'body' => fake('fr_FR')->sentence(10),
            'matched_kb_id' => null,
        ];
    }

    public function fromAssistant(?AssistantKb $kb = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'matched_kb_id' => $kb?->getKey(),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => AssistantMessage::ROLE_SYSTEM]);
    }
}
