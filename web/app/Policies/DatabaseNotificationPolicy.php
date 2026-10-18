<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DatabaseNotification;
use App\Models\User;

/**
 * Cloche (P6-07) : une notification n'est lue, marquée ou supprimée que par son destinataire. L'API borne déjà ses
 * requêtes au compte (404 pour une notification d'autrui) ; la policy reste le garde-fou de tout autre appelant.
 */
final class DatabaseNotificationPolicy
{
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    public function delete(User $user, DatabaseNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    private function owns(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }
}
