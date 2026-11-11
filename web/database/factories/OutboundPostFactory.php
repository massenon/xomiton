<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OutboundKind;
use App\Enums\OutboundStatus;
use App\Models\GatewayTemplate;
use App\Models\OutboundPost;
use App\Models\SocialChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboundPost>
 */
class OutboundPostFactory extends Factory
{
    protected $model = OutboundPost::class;

    /** @var list<array{0: string, 1: string}> bandes dessinées SSR du kit CCC (x_ccc_bd() : clé, titre) */
    private const BDS = [
        ['bd1', 'Léa et le « non »'],
        ['bd2', 'Message reçu, que faire ?'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => OutboundKind::Custom,
            'reference' => null,
            'message' => fake()->realTextBetween(OutboundPost::MESSAGE_MIN_LENGTH + 10, 240),
            'media_path' => null,
            'scheduled_at' => fake()->dateTimeBetween('+1 hour', '+7 days'),
            'status' => OutboundStatus::Scheduled,
            'published_at' => null,
            'created_by' => User::factory(),
        ];
    }

    /** Publication d'une BD SSR du kit CCC : la référence est la clé de la BD (`bd1`, `bd2`). */
    public function bd(): static
    {
        return $this->state(function (array $attributes): array {
            [$key] = fake()->randomElement(self::BDS);

            return [
                'kind' => OutboundKind::Bd,
                'reference' => $key,
                'media_path' => 'ccc/bd/'.fake()->slug(3).'.jpg',
            ];
        });
    }

    /** Publication d'un message clé : la référence est l'identifiant du modèle, le message reprend son texte. */
    public function fromTemplate(?GatewayTemplate $template = null): static
    {
        return $this->state(function (array $attributes) use ($template): array {
            $template ??= GatewayTemplate::factory()->create();

            return [
                'kind' => OutboundKind::Template,
                'reference' => $template->reference(),
                'message' => $template->body,
            ];
        });
    }

    /** Échéance déjà passée, encore planifiée : retenue par `due()`. */
    public function due(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scheduled_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutboundStatus::Published,
            'scheduled_at' => now()->subDay(),
            'published_at' => now()->subDay()->addMinutes(2),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => OutboundStatus::Cancelled]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutboundStatus::Failed,
            'scheduled_at' => now()->subHour(),
        ]);
    }

    /**
     * Cible les réseaux donnés : une remise en attente par passerelle.
     *
     * @param  list<SocialChannel>  $channels
     */
    public function targeting(array $channels): static
    {
        return $this->afterCreating(function (OutboundPost $post) use ($channels): void {
            foreach ($channels as $channel) {
                $post->deliveries()->create(['social_channel_id' => $channel->getKey()]);
            }
        });
    }
}
