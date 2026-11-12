<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut d'une suggestion dans la boîte de l'équipe (P18-14 ; `suggestions.status`).
 */
enum SuggestionStatus: string
{
    case New = 'nouvelle';
    case InProgress = 'en_cours';
    case Kept = 'retenue';
    case Dismissed = 'ecartee';

    public function label(): string
    {
        return (string) __('enums/community.suggestion_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::Kept => 'success',
            self::Dismissed => 'secondary',
        };
    }
}
