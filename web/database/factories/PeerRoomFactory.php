<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\PeerRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PeerRoom>
 */
class PeerRoomFactory extends Factory
{
    protected $model = PeerRoom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Salon '.fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.7)->sentence(10),
            'is_active' => true,
            'is_moderated' => true,
            'allow_anonymous' => true,
            'max_members' => PeerRoom::DEFAULT_MAX_MEMBERS,
            'conversation_id' => null,
            'created_by' => User::factory(),
        ];
    }

    /** Salon rattaché à une conversation de groupe, créée par le même compte que le salon. */
    public function withConversation(?Conversation $conversation = null): static
    {
        return $this->state(function (array $attributes) use ($conversation): array {
            if ($conversation !== null) {
                return ['conversation_id' => $conversation->getKey()];
            }

            $creatorId = $attributes['created_by'] instanceof Factory
                ? User::factory()->create()->getKey()
                : $attributes['created_by'];

            return [
                'created_by' => $creatorId,
                'conversation_id' => Conversation::factory()->group()->state(['created_by' => $creatorId]),
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function unmoderated(): static
    {
        return $this->state(fn (array $attributes): array => ['is_moderated' => false]);
    }

    public function membersOnly(): static
    {
        return $this->state(fn (array $attributes): array => ['allow_anonymous' => false]);
    }

    public function maxMembers(int $max): static
    {
        return $this->state(fn (array $attributes): array => ['max_members' => $max]);
    }
}
