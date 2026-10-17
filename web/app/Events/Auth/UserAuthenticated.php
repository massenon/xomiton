<?php

declare(strict_types=1);

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Mot de passe vérifié : la session s'ouvre, ou un code OTP `login` est attendu (Q13 b). */
final class UserAuthenticated
{
    use Dispatchable;

    public function __construct(public readonly User $user, public readonly bool $requiresOtp) {}
}
