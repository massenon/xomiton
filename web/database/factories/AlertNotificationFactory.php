<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
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
            'recipient' => '+229 01 '.fake()->numerify('## ## ## ##'),
            'directory_contact_id' => null,
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'last_error' => null,
            'sent_at' => null,
            'next_retry_at' => null,
        ];
    }

    public function channel(NotificationChannel $channel): static
    {
        return $this->state(fn (array $attributes): array => match ($channel) {
            NotificationChannel::Mail => ['channel' => $channel, 'recipient' => fake()->safeEmail()],
            NotificationChannel::Sms => ['channel' => $channel],
            NotificationChannel::Push, NotificationChannel::Database => [
                'channel' => $channel,
                'recipient' => AlertNotification::USER_RECIPIENT_PREFIX.User::factory()->create()->id,
            ],
        });
    }

    public function forContact(?DirectoryContact $contact = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'directory_contact_id' => $contact?->id ?? DirectoryContact::factory(),
        ]);
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
        return $this->failed('Plafond de rejeux atteint', (int) config('xomiton.alerts.max_retry_attempts', 10));
    }
}
