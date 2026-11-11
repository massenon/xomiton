<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GroupPrivacy;
use App\Models\Group;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'privacy' => GroupPrivacy::Public,
            'zone_id' => null,
            'image_path' => null,
            'cover_path' => 'covers/gr-default.jpg',
            'owner_id' => User::factory(),
            'members_count' => 0,
            'posts_count' => 0,
            'is_active' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => GroupPrivacy::Private,
        ]);
    }

    /**
     * Groupe rattaché à un département (sinon national).
     */
    public function inZone(?Zone $zone = null): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zone?->id ?? Zone::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
