<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Code incorrect, expiré ou nombre d'essais épuisé : même message dans tous les cas (MICROCOPY §4). */
final class OtpInvalid extends OtpException
{
    public function __construct(public readonly bool $exhausted = false)
    {
        parent::__construct('auth.otp.invalid');
    }
}
