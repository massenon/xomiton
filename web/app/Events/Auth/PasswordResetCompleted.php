<?php

declare(strict_types=1);

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Mot de passe réinitialisé par code ou lien magique : sessions et jetons révoqués. */
final class PasswordResetCompleted
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
