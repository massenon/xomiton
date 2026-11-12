<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Niveau d'une annonce globale (P18-13 ; `announcements.level`) : information (bleu) ou important (orange). Jamais le
 * rouge, réservé à l'urgence (SOS).
 */
enum AnnouncementLevel: string
{
    case Info = 'info';
    case Important = 'important';

    public function label(): string
    {
        return (string) __('enums/platform.announcement_level.'.$this->value);
    }

    public function badge(): string
    {
        return $this === self::Important ? 'warning' : 'info';
    }
}
