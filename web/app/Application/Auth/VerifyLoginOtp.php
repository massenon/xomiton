<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\OtpPurpose;
use App\Models\User;
use App\Support\Audit;

/**
 * Seconde étape de la connexion (Q13 b) : le code `login` saisi ouvre la session ; l'appelant mémorise ensuite
 * l'appareil (DeviceTrust::trustDevice) et connecte la personne.
 */
final class VerifyLoginOtp
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * @throws OtpInvalid
     */
    public function __invoke(User $user, string $code): void
    {
        if (! $this->otp->verify($user->email, OtpPurpose::Login, $code)) {
            throw new OtpInvalid;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        Audit::log('auth.otp_verified', $user, 'usage=login', $user);
    }
}
