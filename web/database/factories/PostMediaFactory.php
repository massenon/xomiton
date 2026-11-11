<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMedia>
 */
class PostMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'kind' => PostMedia::KIND_IMAGE,
            // Visuels du prototype (assets/images/xomiton/hero-01.jpg à hero-16.jpg).
            'path' => sprintf('posts/hero-%02d.jpg', fake()->numberBetween(1, 16)),
            'alt' => fake()->optional(0.6)->sentence(6),
            'width' => 1200,
            'height' => 800,
            'size' => fake()->numberBetween(80_000, 900_000),
            'position' => 0,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => PostMedia::KIND_VIDEO,
            'path' => 'posts/'.fake()->uuid().'.mp4',
            'width' => 1280,
            'height' => 720,
        ]);
    }

    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => PostMedia::KIND_AUDIO,
            'path' => 'posts/'.fake()->uuid().'.mp3',
            'width' => null,
            'height' => null,
        ]);
    }
}
