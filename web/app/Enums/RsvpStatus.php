<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Réponse d'un·e membre à un événement : participe, intéressé·e ou décline.
 * Seul « going » alimente le compteur `events.going_count`.
 */
enum RsvpStatus: string
{
    case Going = 'going';
    case Interested = 'interested';
    case Declined = 'declined';

    public function label(): string
    {
        return (string) __('enums/community.rsvp_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Going => 'success',
            self::Interested => 'info',
            self::Declined => 'secondary',
        };
    }

    public function countsAsGoing(): bool
    {
        return $this === self::Going;
    }
}
