<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\OtpCode;
use App\Models\User;

/**
 * Issue d'un mot de passe vérifié : soit la session peut s'ouvrir, soit un code OTP `login` vient d'être envoyé
 * (appareil non reconnu ou rôle d'administration, Q13 b). `pendingValidation` affiche le bandeau « compte en attente ».
 */
final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public bool $requiresOtp,
        public bool $pendingValidation,
        public ?OtpCode $otp = null,
    ) {}
}
