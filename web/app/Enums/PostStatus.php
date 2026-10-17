<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut de modération des publications, commentaires et sujets de forum.
 * « masque » = retiré du fil par la modération, « supprime » = retiré par l'auteur·rice.
 */
enum PostStatus: string
{
    case Published = 'publie';
    case Hidden = 'masque';
    case Deleted = 'supprime';

    public function label(): string
    {
        return (string) __('enums/feed.post_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Hidden => 'warning',
            self::Deleted => 'danger',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
