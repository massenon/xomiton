<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Invitation impossible (P8-06) : compte inconnu, soi-même, inactif, sans accès au réseau, profil non visible, blocage
 * dans un sens ou dans l'autre, ou refus récent de la même personne. Un seul message et un seul code pour tous ces cas :
 * la réponse ne révèle ni un blocage, ni un refus, ni l'existence d'un compte.
 */
final class InvitationUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.follow.errors.unavailable');
    }

    public function errorCode(): string
    {
        return 'invitation_unavailable';
    }
}
