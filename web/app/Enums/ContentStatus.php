<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut éditorial des contenus publiés par slug (thématiques, ressources) et des événements
 * (DATABASE_DICTIONARY.md §3). « masque » = retiré de l'affichage sans suppression.
 */
enum ContentStatus: string
{
    case Draft = 'brouillon';
    case Published = 'publie';
    case Hidden = 'masque';

    public function label(): string
    {
        return (string) __('enums/content.content_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Published => 'success',
            self::Hidden => 'warning',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
