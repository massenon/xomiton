<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catégorie d'un terme filtré (`bad_words.category`, lot IOS-10) et des motifs de structure du filtre de contenus :
 * sert au journal d'audit (jamais le texte), aux compteurs et au tri dans l'administration.
 */
enum BadWordCategory: string
{
    case Insult = 'insulte';
    case Hate = 'haine';
    case Violence = 'violence';
    case Contact = 'coordonnees';
    case Link = 'lien';
    case Other = 'autre';

    public function label(): string
    {
        return (string) __('enums/content.bad_word_category.'.$this->value);
    }

    /** Catégories qu'un administrateur peut donner à un mot (coordonnées et liens sont des motifs, réglés en configuration). */
    public static function forWords(): array
    {
        return [self::Insult, self::Hate, self::Violence, self::Other];
    }
}
