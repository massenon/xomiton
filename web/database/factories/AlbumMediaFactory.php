<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Album;
use App\Models\AlbumMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlbumMedia>
 */
class AlbumMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'album_id' => Album::factory(),
            // Téléversements du disque public (albums/<ulid>/<fichier>) ; le nom est aléatoire mais jamais comparé.
            'path' => 'albums/'.fake()->uuid().'.jpg',
            'kind' => AlbumMedia::KIND_IMAGE,
            'alt' => fake()->optional(0.6)->sentence(6),
            'width' => 1200,
            'height' => 800,
            'size' => fake()->numberBetween(80_000, 900_000),
            'position' => 0,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => AlbumMedia::KIND_VIDEO,
            'path' => 'albums/'.fake()->uuid().'.mp4',
            'width' => 1280,
            'height' => 720,
        ]);
    }
}
