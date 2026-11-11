<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Identifiant inconnu ou mot de passe incorrect : un seul message, indifférencié (pas d'énumération de comptes). */
final class AuthFailed extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.failed');
    }
}
