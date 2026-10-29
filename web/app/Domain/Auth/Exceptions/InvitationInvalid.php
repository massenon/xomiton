<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/** Lien ou code d'invitation à l'équipe inconnu, expiré, déjà utilisé, annulé ou lié à une autre adresse (message unique). */
final class InvitationInvalid extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.invitation_invalid');
    }
}
