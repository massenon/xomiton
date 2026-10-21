<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Auth\RequestPasswordReset;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Réinitialisation du mot de passe par l'équipe (P12-01) : `users.manage` (UserPolicy `resetPassword`, RG-15). Jamais de
 * mot de passe temporaire : le même e-mail `reinitialisation-mot-de-passe` que « Mot de passe oublié » (code OTP `reset`
 * et lien signé) part vers l'adresse du compte. Journal `users.password_reset_sent`.
 */
final class SendPasswordReset
{
    public function __construct(private readonly RequestPasswordReset $reset) {}

    public function __invoke(User $target, User $by): void
    {
        Gate::forUser($by)->authorize('resetPassword', $target);

        ($this->reset)((string) $target->email);

        Audit::log('users.password_reset_sent', $target, sprintf('pseudo=%s', $target->pseudo), $by);
    }
}
