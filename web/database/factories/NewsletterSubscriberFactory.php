<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    protected $model = NewsletterSubscriber::class;

    /**
     * Par défaut : abonnement confirmé depuis le pied de page, sans tag ni centre d'intérêt.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-6 months', '-1 day');

        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'status' => SubscriberStatus::Active,
            'tags' => [],
            'interests' => [],
            'source' => SubscriberSource::Footer,
            'token' => NewsletterSubscriber::generateToken(),
            'confirmed_at' => fake()->dateTimeBetween($createdAt, 'now'),
            'confirmation_sent_at' => $createdAt,
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
            'bounced_at' => null,
            'bounce_reason' => null,
            'consent_meta' => $createdAt->format('Y-m-d H:i:s').' · formulaire',
            'user_id' => null,
            'last_opened_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /** En attente de confirmation (double opt-in non terminé). */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriberStatus::Pending,
            'confirmed_at' => null,
        ]);
    }

    public function unsubscribed(?string $reason = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'unsubscribe_reason' => $reason ?? 'Trop d’e-mails',
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriberStatus::Bounced,
            'bounced_at' => now()->subDays(fake()->numberBetween(1, 30)),
            'bounce_reason' => 'Adresse inexistante (550)',
        ]);
    }

    public function fromSource(SubscriberSource $source): static
    {
        return $this->state(fn (array $attributes): array => ['source' => $source]);
    }

    /** Abonnement relié à un compte membre. */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user?->getKey() ?? User::factory(),
            'source' => SubscriberSource::App,
        ]);
    }

    /**
     * Tags posés après création : colonne json et table de liaison (les tags sont créés s'ils manquent).
     *
     * @param  list<string>  $slugs
     */
    public function withTags(array $slugs): static
    {
        return $this->afterCreating(function (NewsletterSubscriber $subscriber) use ($slugs): void {
            foreach ($slugs as $slug) {
                NewsletterTag::query()->firstOrCreate(['slug' => $slug], ['label' => ucfirst($slug)]);
            }

            $subscriber->syncTags($slugs);
        });
    }

    /**
     * Centres d'intérêt posés après création (créés s'ils manquent).
     *
     * @param  list<string>  $slugs
     */
    public function withInterests(array $slugs): static
    {
        return $this->afterCreating(function (NewsletterSubscriber $subscriber) use ($slugs): void {
            foreach ($slugs as $slug) {
                NewsletterInterest::query()->firstOrCreate(['slug' => $slug], ['label' => ucfirst($slug)]);
            }

            $subscriber->syncInterests($slugs);
        });
    }
}
