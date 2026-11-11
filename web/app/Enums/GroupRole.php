<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rôle d'un·e membre dans un groupe.
 */
enum GroupRole: string
{
    case Member = 'membre';
    case Moderator = 'moderateur';
    case Admin = 'admin';

    public function label(): string
    {
        return (string) __('enums/feed.group_role.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Member => 'secondary',
            self::Moderator => 'info',
            self::Admin => 'primary',
        };
    }

    public function canModerate(): bool
    {
        return $this !== self::Member;
    }
}
