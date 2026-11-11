<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PushSubscription;
use App\Models\User;

/**
 * Abonnements push (P6-09) : seul le compte abonné les voit et les supprime. L'endpoint d'un autre compte n'est
 * jamais exposé ni supprimé par l'API (App\Application\Account\PushSubscriptions borne ses requêtes au compte).
 */
final class PushSubscriptionPolicy
{
    public function view(User $user, PushSubscription $subscription): bool
    {
        return (int) $subscription->user_id === (int) $user->getKey();
    }

    public function delete(User $user, PushSubscription $subscription): bool
    {
        return $this->view($user, $subscription);
    }
}
