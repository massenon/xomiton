<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut d'une invitation à se suivre (« Invitations » du fil).
 */
enum InvitationStatus: string
{
    case Pending = 'en_attente';
    case Accepted = 'acceptee';
    case Refused = 'refusee';

    public function label(): string
    {
        return (string) __('enums/feed.invitation_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Refused => 'secondary',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
