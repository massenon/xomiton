<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\OtpPurpose;
use App\Events\Auth\EmailVerified;
use App\Models\User;
use App\Support\Audit;

/**
 * Confirmation différée de l'e-mail (Q12 c) : le code `signup` valide `users.email_verified_at` et lève l'accès limité.
 */
final class VerifyEmail
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * @throws OtpInvalid
     */
    public function __invoke(User $user, string $code): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }

        if (! $this->otp->verify($user->email, OtpPurpose::Signup, $code)) {
            throw new OtpInvalid;
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        Audit::log('auth.email_verified', $user, null, $user);
        EmailVerified::dispatch($user);
    }
}
