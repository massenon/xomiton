<?php

declare(strict_types=1);

namespace App\Enums;

use App\Notifications\AlertRouted;
use App\Notifications\AlertStatusChanged;
use App\Notifications\SosAcknowledged;
use App\Notifications\SosCancelled;
use App\Notifications\SosSafe;
use App\Notifications\SosTriggered;
use App\Notifications\SosUnacknowledged;

/**
 * Ce qui a été notifié pour une alerte (`alert_notifications.purpose`, P5-03) : nécessaire au rejeu pour reconstruire
 * la notification et à la règle « une notification par événement et par personne » (NOTIFICATIONS §4.2, index unique).
 */
enum NotificationPurpose: string
{
    case SosTriggered = 'sos_triggered';
    case AlertRouted = 'alert_routed';
    case AlertReminder = 'alert_reminder';
    // Escalade d'un signalement / besoin sans accusé à 48 h (Q48) : « alerte routée » à la nouvelle cible.
    case AlertEscalated = 'alert_escalated';
    case SosUnacknowledged = 'sos_unacknowledged';
    case SosCancelled = 'sos_cancelled';
    case SosSafe = 'sos_safe';
    case SosAcknowledged = 'sos_acknowledged';
    case AlertStatusChanged = 'alert_status_changed';

    public function label(): string
    {
        return (string) __('enums/alerts.notification_purpose.'.$this->value);
    }

    /** Rejoué par `notifications:retry` : seules les notifications qui demandent une action le sont. */
    public function isRetryable(): bool
    {
        return in_array($this, [self::SosTriggered, self::AlertRouted, self::AlertReminder, self::AlertEscalated, self::SosUnacknowledged], true);
    }

    public function isSos(): bool
    {
        return in_array($this, [self::SosTriggered, self::SosUnacknowledged, self::SosCancelled, self::SosSafe, self::SosAcknowledged], true);
    }

    /** SMS de repli (push absent ou en échec) : SOS déclenché et escalade d'un SOS (NOTIFICATIONS §1). */
    public function allowsSmsFallback(): bool
    {
        return $this === self::SosTriggered || $this === self::SosUnacknowledged;
    }

    /**
     * @return list<self>
     */
    public static function retryable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $purpose): bool => $purpose->isRetryable()));
    }

    /**
     * @return class-string<\App\Notifications\XomitonNotification>
     */
    public function notificationClass(): string
    {
        return match ($this) {
            self::SosTriggered => SosTriggered::class,
            self::AlertRouted, self::AlertReminder, self::AlertEscalated => AlertRouted::class,
            self::SosUnacknowledged => SosUnacknowledged::class,
            self::SosCancelled => SosCancelled::class,
            self::SosSafe => SosSafe::class,
            self::SosAcknowledged => SosAcknowledged::class,
            self::AlertStatusChanged => AlertStatusChanged::class,
        };
    }
}
