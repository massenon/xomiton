<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'envoi d'une notification d'alerte (DATABASE_DICTIONARY.md §5 `alert_notifications.status`).
 * `skipped` (P5-03) : abandonnée sans envoi (abonnement disparu, numéro absent, alerte prise en charge ou annulée,
 * plafond de rejeux atteint) ; jamais rejouée. Distinct de `GatewayDeliveryStatus` (passerelles réseaux sociaux, lot 8).
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return (string) __('enums/alerts.delivery_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Sent || $this === self::Skipped;
    }
}
