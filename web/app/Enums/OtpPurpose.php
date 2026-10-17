<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case Signup = 'signup';
    case Reset = 'reset';

    public function label(): string
    {
        return __('enums/identity.otp_purpose.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Login => 'primary',
            self::Signup => 'success',
            self::Reset => 'warning',
        };
    }
}
