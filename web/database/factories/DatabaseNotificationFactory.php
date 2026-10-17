<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DatabaseNotification>
 */
class DatabaseNotificationFactory extends Factory
{
    protected $model = DatabaseNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CommunityNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'data' => [
                'title' => fake()->sentence(4),
                'body' => fake()->sentence(10),
                'url' => '/fil',
            ],
            'category' => DatabaseNotification::CATEGORY_COMMUNITY,
            'is_sensitive' => false,
            'read_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
        ]);
    }

    public function inCategory(string $category): static
    {
        return $this->state(fn (array $attributes): array => ['category' => $category]);
    }

    /** Contenu masqué dans la cloche (alerte, SOS, modération). */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => DatabaseNotification::CATEGORY_SECURITY,
            'is_sensitive' => true,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
        ]);
    }
}
