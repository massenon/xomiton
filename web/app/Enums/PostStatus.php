<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut de modération des publications, commentaires et sujets de forum.
 * « masque » = retiré du fil par la modération, « supprime » = retiré par l'auteur·rice. Vidéos (Q96) : « relecture » =
 * en attente de la modération (invisible hors auteur·rice et modération), « refuse » = refusée à la relecture.
 */
enum PostStatus: string
{
    case Published = 'publie';
    case Hidden = 'masque';
    case Deleted = 'supprime';
    case Pending = 'relecture';
    case Rejected = 'refuse';

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
            self::Pending => 'info',
            self::Rejected => 'danger',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    /** Vidéo en attente de relecture ou refusée : visible de l'auteur·rice et de la modération seulement. */
    public function isUnderReview(): bool
    {
        return $this === self::Pending || $this === self::Rejected;
    }
}
