<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Accès à l'espace pair (tâches, collecte, dossiers, alertes routées) : permission `peer_space.access` (pair,
 * administration) **et** compte actif — RG-03 : un compte pair en attente de validation reste « membre » et n'accède
 * pas à la collecte ; un compte suspendu non plus. Même règle que MemberDashboard::hasPeerSpace (section `peer`).
 */
final class PeerSpace
{
    private function __construct() {}

    public static function allows(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->can('peer_space.access');
    }
}
