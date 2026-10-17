<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterClick>
 */
class NewsletterClickFactory extends Factory
{
    protected $model = NewsletterClick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->randomElement([
            'https://xomiton.org/thematiques.php',
            'https://xomiton.org/events.php',
            'https://xomiton.org/ressources.php',
        ]);

        return [
            'recipient_id' => NewsletterCampaignRecipient::factory()->opened(),
            'url' => $url,
            'url_hash' => NewsletterClick::hashUrl($url),
            'clicked_at' => now()->subMinutes(fake()->numberBetween(1, 300)),
            'clicks_count' => 1,
        ];
    }

    public function forRecipient(NewsletterCampaignRecipient $recipient): static
    {
        return $this->state(fn (array $attributes): array => ['recipient_id' => $recipient->getKey()]);
    }

    public function onUrl(string $url): static
    {
        return $this->state(fn (array $attributes): array => [
            'url' => $url,
            'url_hash' => NewsletterClick::hashUrl($url),
        ]);
    }
}
