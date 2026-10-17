<?php

declare(strict_types=1);

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Compte pair éducateur·rice validé par l'administration (RG-03, FR-AUTH-05). */
final class PeerValidated
{
    use Dispatchable;

    public function __construct(public readonly User $peer, public readonly User $validatedBy) {}
}
