<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Journalise la réinitialisation d'un mot de passe (P2-05, P2-09) ; seul le compte concerné est tracé, jamais le secret.
 */
final class LogPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        Audit::log('auth.password_reset', $user, null, $user);
    }
}
