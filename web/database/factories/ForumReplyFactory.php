<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReply>
 */
class ForumReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => ForumTopic::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(15),
            'is_anonymous' => false,
            'status' => PostStatus::Published,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Hidden,
        ]);
    }

    public function forTopic(ForumTopic $topic): static
    {
        return $this->state(fn (array $attributes) => [
            'topic_id' => $topic->id,
        ]);
    }
}
