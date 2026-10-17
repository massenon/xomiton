<?php

declare(strict_types=1);

namespace App\Enums;

enum ProfileVisibility: string
{
    case Public = 'public';
    case Community = 'community';
    case Followers = 'followers';
    case Private = 'private';

    public function label(): string
    {
        return __('enums/identity.profile_visibility.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Community => 'info',
            self::Followers => 'primary',
            self::Private => 'secondary',
        };
    }
}
