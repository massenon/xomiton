<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'une campagne newsletter (DATABASE_DICTIONARY.md §9 `newsletter_campaigns.status`).
 */
enum CampaignStatus: string
{
    case Draft = 'brouillon';
    case Scheduled = 'planifiee';
    case Sending = 'en_cours';
    case Sent = 'envoyee';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return (string) __('enums/communication.campaign_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Scheduled => 'info',
            self::Sending => 'warning',
            self::Sent => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Contenu et segment encore modifiables : avant le début de l'envoi. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Scheduled;
    }

    /** Campagne terminée : plus aucune transition possible. */
    public function isFinal(): bool
    {
        return $this === self::Sent || $this === self::Cancelled;
    }

    /**
     * Valeur du prototype (`newsletter_campaigns.status`, identique aux valeurs stockées).
     */
    public static function fromPrototype(string $status): self
    {
        return self::tryFrom(mb_strtolower(trim($status))) ?? self::Draft;
    }
}
