<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/**
 * Compte banni (P18-06, Q97) : connexion refusée avec un message **neutre** (ni « banni » ni le motif, qui a été notifié
 * à la personne), signalé seulement après vérification du mot de passe ou du code, comme une suspension.
 */
final class AccountUnavailable extends AuthException
{
    public function __construct(string $supportEmail)
    {
        parent::__construct('auth.unavailable', ['email' => $supportEmail]);
    }

    public function status(): int
    {
        return 403;
    }
}
