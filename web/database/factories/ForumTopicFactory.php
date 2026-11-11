<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Forum;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumTopic>
 */
class ForumTopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_id' => Forum::factory(),
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(8), '.').' ?',
            'body' => fake()->paragraph(3),
            'is_anonymous' => false,
            'views_count' => fake()->numberBetween(0, 300),
            'replies_count' => 0,
            'last_reply_at' => null,
            'is_pinned' => false,
            'is_locked' => false,
            'status' => PostStatus::Published,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Hidden,
        ]);
    }
}
