<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut éditorial des contenus publiés par slug (thématiques, ressources) et des événements
 * (DATABASE_DICTIONARY.md §3). « masque » = retiré de l'affichage sans suppression. « en_attente » (P18-14) : événement
 * créé par un·e membre quand la file d'approbation est activée, invisible jusqu'à la décision de la modération.
 */
enum ContentStatus: string
{
    case Draft = 'brouillon';
    case Published = 'publie';
    case Hidden = 'masque';
    case Pending = 'en_attente';

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
            self::Pending => 'info',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
