<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Invitation refusée à l'envoi (P8-06) : la personne a déjà invité celle qui envoie (elle la voit dans « Invitations
 * reçues ») ; il suffit de l'accepter.
 */
final class InvitationFromThem extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.follow.errors.invited_you');
    }

    public function errorCode(): string
    {
        return 'invitation_from_them';
    }
}
