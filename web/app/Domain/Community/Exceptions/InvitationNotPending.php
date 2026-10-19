<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Réponse ou annulation impossible (P8-06) : l'invitation a déjà été acceptée ou refusée. Répéter la même réponse reste
 * sans effet (200).
 */
final class InvitationNotPending extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.follow.errors.not_pending');
    }

    public function errorCode(): string
    {
        return 'invitation_not_pending';
    }
}
