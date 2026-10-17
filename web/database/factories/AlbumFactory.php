<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProfileVisibility;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    /** @var list<string> titres des albums du prototype (albums.php) */
    private const TITLES = ['Atelier sororité', 'Causerie santé', 'Marche des fiertés', 'Souvenirs du groupe', 'Portraits'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => Album::KIND_CUSTOM,
            'title' => fake()->randomElement(self::TITLES).' '.fake()->numberBetween(2024, 2026),
            'description' => fake()->optional(0.6)->sentence(8),
            'visibility' => ProfileVisibility::Community,
            'media_count' => 0,
            'cover_media_id' => null,
        ];
    }

    /** Album système des photos de profil. */
    public function profilePictures(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => Album::KIND_PROFILE,
            'title' => 'Photos de profil',
        ]);
    }

    /** Album système des photos de couverture. */
    public function covers(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => Album::KIND_COVER,
            'title' => 'Photos de couverture',
        ]);
    }

    public function visibility(ProfileVisibility $visibility): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => $visibility,
        ]);
    }

    /**
     * Ajoute des médias ordonnés, met à jour le compteur et désigne le premier comme couverture.
     */
    public function withMedia(int $count = 3): static
    {
        return $this->afterCreating(function (Album $album) use ($count): void {
            $media = AlbumMedia::factory()
                ->count($count)
                ->sequence(fn ($sequence) => ['position' => $sequence->index])
                ->create(['album_id' => $album->id]);

            $album->forceFill([
                'media_count' => $media->count(),
                'cover_media_id' => $media->first()?->id,
            ])->saveQuietly();
        });
    }
}
