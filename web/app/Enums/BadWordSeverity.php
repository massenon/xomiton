<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Gravité d'un terme filtré par la modération automatique (DATABASE_DICTIONARY.md §3 `bad_words`) :
 * « bloquer » refuse la publication, « signaler » la laisse passer en la marquant pour relecture.
 */
enum BadWordSeverity: string
{
    case Block = 'bloquer';
    case Flag = 'signaler';

    public function label(): string
    {
        return (string) __('enums/content.bad_word_severity.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Block => 'danger',
            self::Flag => 'warning',
        };
    }

    public function blocks(): bool
    {
        return $this === self::Block;
    }
}
