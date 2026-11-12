<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Public d'une annonce globale (P18-13 ; `announcements.audience`) : tous les comptes de l'espace social, ou les pair·es
 * seulement (rôle `pair`). Jamais les visiteur·ses (Q97).
 */
enum AnnouncementAudience: string
{
    case Members = 'membres';
    case Peers = 'pairs';

    public function label(): string
    {
        return (string) __('enums/platform.announcement_audience.'.$this->value);
    }
}
