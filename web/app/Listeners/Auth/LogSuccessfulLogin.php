<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Login;

/**
 * Journalise chaque connexion réussie (P2-09), avec le garde et l'option « Rester connecté·e ».
 */
final class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        Audit::log(
            'auth.login',
            $user,
            sprintf('garde=%s ; rester_connecte=%s', $event->guard, $event->remember ? 'oui' : 'non'),
            $user,
        );
    }
}
