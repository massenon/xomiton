<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'en_attente';
    case Active = 'actif';
    case Suspended = 'suspendu';

    public function label(): string
    {
        return __('enums/identity.user_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
