<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État de la remise d'une publication sur un réseau (DATABASE_DICTIONARY.md §8 `outbound_deliveries.status`).
 * `manuel` : publiée à la main par l'équipe sur un réseau sans API, puis marquée comme telle.
 * Distinct de `DeliveryStatus` (notifications d'alerte, lot 6).
 */
enum GatewayDeliveryStatus: string
{
    case Pending = 'en_attente';
    case Published = 'publie';
    case Failed = 'echec';
    case Manual = 'manuel';

    public function label(): string
    {
        return (string) __('enums/gateways.gateway_delivery_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Failed => 'danger',
            self::Manual => 'info',
        };
    }

    /** La publication est effectivement en ligne (par l'API ou à la main). */
    public function isDelivered(): bool
    {
        return $this === self::Published || $this === self::Manual;
    }
}
