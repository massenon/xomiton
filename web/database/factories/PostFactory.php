<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Group;
use App\Models\Page;
use App\Models\Poll;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'group_id' => null,
            'page_id' => null,
            'is_anonymous' => false,
            'body' => fake()->paragraph(3),
            'tag' => fake()->randomElement([PostTag::Testimony, PostTag::Question, PostTag::Resource, PostTag::Event]),
            'visibility' => PostVisibility::Public,
            'likes_count' => 0,
            'comments_count' => 0,
            'reports_count' => 0,
            'status' => PostStatus::Published,
            'hidden_reason' => null,
            'published_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }

    /**
     * Publication anonyme : l'auteur·rice reste connu·e en base, jamais affiché·e (RG-02).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    /**
     * Publication « Sondage » avec un sondage de 2 à 6 options (RG-09, borné par PollFactory).
     */
    public function poll(int $options = 4): static
    {
        return $this->state(fn (array $attributes) => [
            'tag' => PostTag::Poll,
        ])->afterCreating(function (Post $post) use ($options): void {
            Poll::factory()->withOptions($options)->create(['post_id' => $post->id]);
        });
    }

    /**
     * Masquée par la modération.
     */
    public function hidden(string $reason = 'Signalements multiples'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::Hidden,
            'hidden_reason' => $reason,
        ]);
    }

    public function tagged(PostTag $tag): static
    {
        return $this->state(fn (array $attributes) => [
            'tag' => $tag,
        ]);
    }

    public function visibility(PostVisibility $visibility): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => $visibility,
        ]);
    }

    public function inGroup(?Group $group = null): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group?->id ?? Group::factory(),
        ]);
    }

    public function onPage(?Page $page = null): static
    {
        return $this->state(fn (array $attributes) => [
            'page_id' => $page?->id ?? Page::factory(),
        ]);
    }

    public function withImage(): static
    {
        return $this->afterCreating(function (Post $post): void {
            PostMedia::factory()->create(['post_id' => $post->id]);
        });
    }
}
