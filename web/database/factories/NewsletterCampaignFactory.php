<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterCampaign>
 */
class NewsletterCampaignFactory extends Factory
{
    protected $model = NewsletterCampaign::class;

    /**
     * Par défaut : brouillon visant tou·tes les abonné·es actif·ves (`newsletter_campaigns.json`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => 'Xomiton · '.fake()->sentence(4),
            'from_name' => 'Xomiton',
            'reply_to' => 'contact@xomiton.bj',
            'preheader' => 'Les nouvelles de la communauté',
            'body' => '<h2>Bonjour {{user_first_name}} 💜</h2><p>'.fake()->paragraph().'</p>',
            'segment' => [],
            'status' => CampaignStatus::Draft,
            'scheduled_at' => null,
            'started_at' => null,
            'sent_at' => null,
            'recipients_count' => 0,
            'sent_count' => 0,
            'test_sent_to' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * @param  array{tags?: list<string>, interests?: list<string>, since?: string|null, until?: string|null}  $segment
     */
    public function segment(array $segment): static
    {
        return $this->state(fn (array $attributes): array => ['segment' => $segment]);
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => $at ?? fake()->dateTimeBetween('+1 hour', '+7 days'),
        ]);
    }

    /** Planifiée et échue : retenue par `due()`. */
    public function due(): static
    {
        return $this->scheduled(now()->subMinutes(fake()->numberBetween(1, 120)));
    }

    public function sending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CampaignStatus::Sending,
            'scheduled_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function sent(): static
    {
        return $this->state(function (array $attributes): array {
            $sentAt = fake()->dateTimeBetween('-60 days', '-1 day');

            return [
                'status' => CampaignStatus::Sent,
                'scheduled_at' => $sentAt,
                'started_at' => $sentAt,
                'sent_at' => $sentAt,
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CampaignStatus::Cancelled]);
    }

    /**
     * Destinataires créés après la campagne, un par abonné·e donné·e (compteur recalculé).
     *
     * @param  list<NewsletterSubscriber>  $subscribers
     */
    public function targeting(array $subscribers): static
    {
        return $this->afterCreating(function (NewsletterCampaign $campaign) use ($subscribers): void {
            foreach ($subscribers as $subscriber) {
                $campaign->recipients()->create(['subscriber_id' => $subscriber->getKey()]);
            }

            $campaign->forceFill(['recipients_count' => count($subscribers)])->save();
        });
    }
}
