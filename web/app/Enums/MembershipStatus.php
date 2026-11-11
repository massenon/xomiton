<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'une adhésion à un groupe : demande en attente (groupes privés), actif ou banni.
 */
enum MembershipStatus: string
{
    case Requested = 'demande';
    case Active = 'actif';
    case Banned = 'banni';

    public function label(): string
    {
        return (string) __('enums/feed.membership_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Active => 'success',
            self::Banned => 'danger',
        };
    }
}
