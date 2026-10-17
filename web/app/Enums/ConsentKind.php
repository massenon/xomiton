<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentKind: string
{
    case Terms = 'cgu';
    case Privacy = 'privacy';
    case SensitiveData = 'sensitive_data';
    case Newsletter = 'newsletter';

    public function label(): string
    {
        return __('enums/identity.consent_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Terms => 'primary',
            self::Privacy => 'info',
            self::SensitiveData => 'danger',
            self::Newsletter => 'secondary',
        };
    }
}
