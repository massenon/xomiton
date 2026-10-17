<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Logout;

/**
 * Journalise la déconnexion (P2-09). L'utilisateur·rice peut déjà être détaché·e de la session : on le passe explicitement.
 */
final class LogLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        Audit::log('auth.logout', $user, sprintf('garde=%s', $event->guard), $user);
    }
}
