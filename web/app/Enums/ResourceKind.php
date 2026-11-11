<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nature d'une ressource documentaire (DATABASE_DICTIONARY.md §3 `resources.kind`).
 * Les actualités (`/actualites`) sont des ressources `article` tant que le client n'a pas tranché.
 */
enum ResourceKind: string
{
    case Sheet = 'fiche';
    case Guide = 'guide';
    case Video = 'video';
    case Audio = 'audio';
    case Pdf = 'pdf';
    case Article = 'article';
    case Link = 'lien';

    public function label(): string
    {
        return (string) __('enums/content.resource_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Sheet => 'primary',
            self::Guide => 'info',
            self::Video => 'danger',
            self::Audio => 'violet',
            self::Pdf => 'secondary',
            self::Article => 'success',
            self::Link => 'warning',
        };
    }

    /** Icône Bootstrap Icons associée (affichage des listes). */
    public function icon(): string
    {
        return match ($this) {
            self::Sheet => 'bi-file-text',
            self::Guide => 'bi-journal-bookmark',
            self::Video => 'bi-play-circle',
            self::Audio => 'bi-headphones',
            self::Pdf => 'bi-file-earmark-pdf',
            self::Article => 'bi-newspaper',
            self::Link => 'bi-link-45deg',
        };
    }

    /** Ressource portée par un fichier téléversé (plutôt qu'un corps HTML ou un lien externe). */
    public function hasFile(): bool
    {
        return in_array($this, [self::Video, self::Audio, self::Pdf], true);
    }
}
