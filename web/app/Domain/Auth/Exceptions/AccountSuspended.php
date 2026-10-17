<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Compte suspendu : signalé seulement après vérification du mot de passe. */
final class AccountSuspended extends AuthException
{
    public function __construct(string $supportEmail)
    {
        parent::__construct('auth.suspended', ['email' => $supportEmail]);
    }

    public function status(): int
    {
        return 403;
    }
}
