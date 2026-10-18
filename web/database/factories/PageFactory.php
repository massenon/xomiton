<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Catégories du prototype (pages.json).
     *
     * @var list<string>
     */
    public const CATEGORIES = ['Organisation', 'Santé', 'Juridique', 'Réseau'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => Str::limit($name, 120, ''),
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(self::CATEGORIES),
            'description' => fake()->sentence(8),
            'image_path' => null,
            'cover_path' => null,
            'website' => fake()->optional(0.5)->url(),
            'owner_id' => User::factory(),
            'followers_count' => 0,
            'is_verified' => false,
            // Approuvée par défaut (pages d'organisations partenaires) ; `pending()` : en attente de la modération (P8-03).
            'is_approved' => true,
            'approved_at' => now(),
            'is_featured' => false,
            'is_active' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
            'approved_at' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
