<?php

declare(strict_types=1);

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Compte créé en phase 1 de l'inscription (code de vérification envoyé, e-mail de bienvenue). */
final class UserRegistered
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
