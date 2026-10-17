<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use Illuminate\Support\Carbon;

/** Plafond d'envois par heure atteint pour cette adresse et cet usage. */
final class OtpRateLimited extends OtpException
{
    public function __construct(public readonly Carbon $retryAt)
    {
        parent::__construct('auth.otp.rate_limited', ['minutes' => max(1, (int) ceil(now()->diffInMinutes($retryAt, false)))]);
    }

    public function status(): int
    {
        return 429;
    }
}
