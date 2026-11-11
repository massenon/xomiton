<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use Illuminate\Support\Carbon;

/** Renvoi demandé avant le délai minimal (30 s) : le code précédent reste valable. */
final class OtpTooSoon extends OtpException
{
    public function __construct(public readonly Carbon $retryAt)
    {
        parent::__construct('auth.otp.too_soon', ['seconds' => max(1, (int) ceil(now()->diffInSeconds($retryAt, false)))]);
    }

    public function status(): int
    {
        return 429;
    }
}
