<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Group;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::ucfirst(fake()->unique()->words(4, true));
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 months');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'place' => fake()->randomElement(['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi']),
            'is_online' => false,
            'online_url' => null,
            'description' => fake()->sentence(12),
            'image_path' => null,
            'organizer_id' => User::factory(),
            'organizer_label' => null,
            'group_id' => null,
            'page_id' => null,
            'going_count' => 0,
            'status' => ContentStatus::Published,
            'is_public' => false,
            'reminder_sent_at' => null,
        ];
    }

    /**
     * Ouvert aux visiteur·ses (Q35 a, P8-04) : page publique, plan du site, JSON-LD `Event`.
     */
    public function openToPublic(): static
    {
        return $this->state(fn (array $attributes) => ['is_public' => true]);
    }

    /**
     * Adresse exacte (réservée aux participant·es confirmé·es, P8-04).
     */
    public function withAddress(string $address = 'Maison des jeunes, 2e étage, porte bleue'): static
    {
        return $this->state(fn (array $attributes) => ['address' => $address]);
    }

    /**
     * Événement en ligne (lien de visioconférence, sans lieu).
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'place' => 'En ligne',
            'online_url' => fake()->url(),
        ]);
    }

    /**
     * Organisé par un libellé libre (sans compte membre), comme « Équipe Xomiton ».
     */
    public function organizedBy(string $label): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => null,
            'organizer_label' => $label,
        ]);
    }

    public function inCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id ?? Category::factory()->state(['kind' => Category::KIND_EVENT]),
        ]);
    }

    public function forGroup(?Group $group = null): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group?->id ?? Group::factory(),
        ]);
    }

    public function forPage(?Page $page = null): static
    {
        return $this->state(fn (array $attributes) => [
            'page_id' => $page?->id ?? Page::factory(),
        ]);
    }

    /**
     * Événement déjà terminé.
     */
    public function past(): static
    {
        return $this->state(function (array $attributes): array {
            $startsAt = fake()->dateTimeBetween('-2 months', '-2 days');

            return [
                'starts_at' => $startsAt,
                'ends_at' => (clone $startsAt)->modify('+2 hours'),
            ];
        });
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Draft,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Hidden,
        ]);
    }
}
