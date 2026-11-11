<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Lignes `alert_notifications` : le destinataire est toujours une référence (`user:`, `subscription:`, `contact:`,
 * `trusted:`), jamais un numéro ni une adresse (ADR-33, P5-03).
 *
 * @extends Factory<AlertNotification>
 */
class AlertNotificationFactory extends Factory
{
    protected $model = AlertNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'channel' => NotificationChannel::Sms,
            'purpose' => NotificationPurpose::SosTriggered,
            'directory_contact_id' => DirectoryContact::factory(),
            'recipient' => static fn (array $attributes): string => AlertNotification::CONTACT_RECIPIENT_PREFIX.$attributes['directory_contact_id'],
            'user_id' => null,
            'push_subscription_id' => null,
            'status' => DeliveryStatus::Pending,
            'provider' => null,
            'attempts' => 0,
            'last_error' => null,
            'sent_at' => null,
            'seen_at' => null,
            'next_retry_at' => null,
        ];
    }

    public function channel(NotificationChannel $channel): static
    {
        return match ($channel) {
            NotificationChannel::Push => $this->toSubscription(),
            NotificationChannel::Database => $this->toUser(),
            default => $this->state(fn (array $attributes): array => ['channel' => $channel]),
        };
    }

    public function purpose(NotificationPurpose $purpose): static
    {
        return $this->state(fn (array $attributes): array => ['purpose' => $purpose]);
    }

    public function forAlert(Alert $alert): static
    {
        return $this->state(fn (array $attributes): array => ['alert_id' => $alert->getKey()]);
    }

    /** Cloche (ou autre canal) d'un compte : `user:<id>`. */
    public function toUser(?User $user = null, NotificationChannel $channel = NotificationChannel::Database): static
    {
        return $this->state(function (array $attributes) use ($user, $channel): array {
            $user ??= User::factory()->create();

            return [
                'channel' => $channel,
                'recipient' => AlertNotification::USER_RECIPIENT_PREFIX.$user->getKey(),
                'user_id' => $user->getKey(),
                'directory_contact_id' => null,
            ];
        });
    }

    /** Push vers un abonnement d'un compte : `subscription:<id>`. */
    public function toSubscription(?PushSubscription $subscription = null): static
    {
        return $this->state(function (array $attributes) use ($subscription): array {
            $subscription ??= PushSubscription::factory()->create();

            return [
                'channel' => NotificationChannel::Push,
                'recipient' => AlertNotification::SUBSCRIPTION_RECIPIENT_PREFIX.$subscription->getKey(),
                'user_id' => $subscription->user_id,
                'push_subscription_id' => $subscription->getKey(),
                'directory_contact_id' => null,
            ];
        });
    }

    /** SMS au contact de confiance de l'auteur·e : `trusted:<id>`. */
    public function toTrusted(User $author): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => NotificationChannel::Sms,
            'recipient' => AlertNotification::TRUSTED_RECIPIENT_PREFIX.$author->getKey(),
            'user_id' => null,
            'directory_contact_id' => null,
        ]);
    }

    /** Fiche d'annuaire sans compte : `contact:<id>` (SMS ou e-mail). */
    public function toContact(DirectoryContact $contact, NotificationChannel $channel = NotificationChannel::Sms): static
    {
        return $this->state(fn (array $attributes): array => [
            'channel' => $channel,
            'recipient' => AlertNotification::CONTACT_RECIPIENT_PREFIX.$contact->getKey(),
            'directory_contact_id' => $contact->getKey(),
            'user_id' => null,
        ]);
    }

    public function forContact(?DirectoryContact $contact = null): static
    {
        return $this->state(function (array $attributes) use ($contact): array {
            $contact ??= DirectoryContact::factory()->create();

            return [
                'directory_contact_id' => $contact->getKey(),
                'recipient' => AlertNotification::CONTACT_RECIPIENT_PREFIX.$contact->getKey(),
            ];
        });
    }

    public function provider(string $provider): static
    {
        return $this->state(fn (array $attributes): array => ['provider' => $provider]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Sent,
            'attempts' => 1,
            'sent_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function seen(): static
    {
        return $this->sent()->state(fn (array $attributes): array => ['seen_at' => now()]);
    }

    public function skipped(string $reason = AlertNotification::SKIP_GONE): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Skipped,
            'last_error' => $reason,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Échec avec rejeu planifié (déjà échu pour que `dueForRetry()` la retienne).
     */
    public function failed(string $error = 'Fournisseur SMS indisponible', int $attempts = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeliveryStatus::Failed,
            'attempts' => $attempts,
            'last_error' => $error,
            'next_retry_at' => now()->subMinute(),
        ]);
    }

    /**
     * Échec définitif : plafond de rejeux atteint.
     */
    public function exhausted(): static
    {
        return $this->failed(AlertNotification::SKIP_EXHAUSTED, (int) config('xomiton.alerts.max_retry_attempts', 10));
    }
}
