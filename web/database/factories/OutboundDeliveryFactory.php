<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GatewayDeliveryStatus;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\SocialChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboundDelivery>
 */
class OutboundDeliveryFactory extends Factory
{
    protected $model = OutboundDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outbound_post_id' => OutboundPost::factory(),
            'social_channel_id' => SocialChannel::factory()->connected(),
            'status' => GatewayDeliveryStatus::Pending,
            'external_id' => null,
            'external_url' => null,
            'error' => null,
            'attempts' => 0,
            'published_at' => null,
            'marked_by' => null,
        ];
    }

    public function forPost(OutboundPost $post): static
    {
        return $this->state(fn (array $attributes): array => ['outbound_post_id' => $post->getKey()]);
    }

    public function onChannel(SocialChannel $channel): static
    {
        return $this->state(fn (array $attributes): array => ['social_channel_id' => $channel->getKey()]);
    }

    /** Publiée par l'API du réseau. */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GatewayDeliveryStatus::Published,
            'external_id' => (string) fake()->numerify('################'),
            'external_url' => fake()->url(),
            'attempts' => 1,
            'published_at' => now()->subMinutes(5),
        ]);
    }

    /** Publiée à la main sur un réseau sans API, puis marquée par un·e administrateur·rice. */
    public function manual(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GatewayDeliveryStatus::Manual,
            'external_url' => fake()->url(),
            'published_at' => now()->subMinutes(10),
            'marked_by' => $user?->getKey() ?? User::factory(),
        ]);
    }

    public function failed(string $error = 'Réponse 401 du réseau : jeton refusé', int $attempts = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GatewayDeliveryStatus::Failed,
            'error' => $error,
            'attempts' => $attempts,
        ]);
    }

    /** Échec définitif : plafond de tentatives atteint. */
    public function exhausted(): static
    {
        return $this->failed('Plafond de tentatives atteint', OutboundDelivery::maxAttempts());
    }
}
