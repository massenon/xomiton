<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'une publication sortante (DATABASE_DICTIONARY.md §8 `outbound_posts.status`).
 */
enum OutboundStatus: string
{
    case Scheduled = 'planifie';
    case Published = 'publie';
    case Cancelled = 'annule';
    case Failed = 'echec';

    public function label(): string
    {
        return (string) __('enums/gateways.outbound_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Published => 'success',
            self::Cancelled => 'secondary',
            self::Failed => 'danger',
        };
    }

    /** Une publication planifiée peut encore être publiée ou annulée. */
    public function isOpen(): bool
    {
        return $this === self::Scheduled;
    }

    /**
     * Valeur du prototype (`gateway_posts.status` : « planifié », « publié », « annulé »).
     */
    public static function fromPrototype(string $status): self
    {
        return match (mb_strtolower(trim($status))) {
            'planifié', 'planifie' => self::Scheduled,
            'publié', 'publie' => self::Published,
            'annulé', 'annule' => self::Cancelled,
            'échec', 'echec' => self::Failed,
            default => self::Scheduled,
        };
    }
}
