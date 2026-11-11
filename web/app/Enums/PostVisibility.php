<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Audience d'une publication. Le `friends` du prototype devient `followers` à l'import.
 */
enum PostVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case Me = 'me';

    public function label(): string
    {
        return (string) __('enums/feed.post_visibility.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Followers => 'primary',
            self::Me => 'secondary',
        };
    }
}
