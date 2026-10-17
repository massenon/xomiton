<?php

declare(strict_types=1);

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Adresse e-mail confirmée par code : le compte sort de l'accès limité. */
final class EmailVerified
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
