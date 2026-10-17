<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterCampaignRecipient>
 */
class NewsletterCampaignRecipientFactory extends Factory
{
    protected $model = NewsletterCampaignRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => NewsletterCampaign::factory(),
            'subscriber_id' => NewsletterSubscriber::factory(),
            'sent_at' => null,
            'opened_at' => null,
            'bounced' => false,
            'unsubscribed' => false,
            'error' => null,
        ];
    }

    public function forCampaign(NewsletterCampaign $campaign): static
    {
        return $this->state(fn (array $attributes): array => ['campaign_id' => $campaign->getKey()]);
    }

    public function forSubscriber(NewsletterSubscriber $subscriber): static
    {
        return $this->state(fn (array $attributes): array => ['subscriber_id' => $subscriber->getKey()]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sent_at' => now()->subHours(fake()->numberBetween(1, 72)),
        ]);
    }

    public function opened(): static
    {
        return $this->sent()->state(fn (array $attributes): array => [
            'opened_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
        ]);
    }

    public function bounced(): static
    {
        return $this->sent()->state(fn (array $attributes): array => [
            'bounced' => true,
            'error' => '550 Mailbox not found',
        ]);
    }

    public function unsubscribed(): static
    {
        return $this->opened()->state(fn (array $attributes): array => ['unsubscribed' => true]);
    }
}
