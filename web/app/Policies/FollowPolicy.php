<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Abonnements entre membres (P8-06) : `social.access` voit les suggestions « Qui suivre », suit et ne suit plus. Qui
 * peut être suivi·e (profil ouvert, sans blocage) : App\Application\Community\Network\NetworkPeople::canFollow (422
 * neutre, jamais 403, pour ne rien révéler du compte visé).
 */
final class FollowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }
}
