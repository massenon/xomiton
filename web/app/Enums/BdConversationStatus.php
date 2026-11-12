<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut d'une conversation en BD (P20-03 ; `bd_conversations.status`). Seule `publiee` est visible du public et indexée.
 * Cycle des créations des membres (lot B, P20-08) : brouillon (privé) → proposée → publiée ou refusée (motif) ; retirée
 * par l'auteur·rice ou l'équipe. Les modèles importés passent de brouillon à publiée.
 */
enum BdConversationStatus: string
{
    case Draft = 'brouillon';
    case Submitted = 'proposee';
    case Published = 'publiee';
    case Refused = 'refusee';
    case Withdrawn = 'retiree';

    public function label(): string
    {
        return (string) __('conversations_bd.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted => 'info',
            self::Published => 'success',
            self::Refused => 'danger',
            self::Withdrawn => 'warning',
        };
    }
}
