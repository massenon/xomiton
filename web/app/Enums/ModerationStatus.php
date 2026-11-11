<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'un signalement (DATABASE_DICTIONARY.md §7 `moderation_reports.status`).
 */
enum ModerationStatus: string
{
    case Pending = 'en_attente';
    case Handled = 'traite';
    case Rejected = 'rejete';

    public function label(): string
    {
        return (string) __('enums/steering.moderation_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Handled => 'success',
            self::Rejected => 'secondary',
        };
    }

    /** Signalement déjà examiné par la modération. */
    public function isClosed(): bool
    {
        return $this !== self::Pending;
    }
}
