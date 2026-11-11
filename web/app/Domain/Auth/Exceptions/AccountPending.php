<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

/**
 * Action réservée aux comptes validés par l'équipe (publier, commenter, écrire, rejoindre un groupe ou un événement) :
 * le compte est `en attente` (inscription en 3 étapes, décision client du 11/11/2026). Jamais levée pour la lecture, le
 * SOS, les alertes, le signalement ni le profil : la sécurité de la personne ne dépend pas de la validation.
 */
final class AccountPending extends AuthException
{
    public function __construct()
    {
        parent::__construct('auth.pending_restricted');
    }

    public function status(): int
    {
        return 403;
    }
}
