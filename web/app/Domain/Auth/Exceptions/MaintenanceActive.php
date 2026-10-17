<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Maintenance en cours (RG-14) : connexion réservée aux rôles admin et superadmin, après vérification du mot de passe. */
final class MaintenanceActive extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.maintenance');
    }

    public function status(): int
    {
        return 503;
    }
}
