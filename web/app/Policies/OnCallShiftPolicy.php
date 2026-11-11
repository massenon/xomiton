<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OnCallShift;
use App\Models\User;

/**
 * Planning de garde (P1-17) : tout passe par `on_call.manage` (admin et superadmin) ; le planning pilote le routage des SOS.
 */
final class OnCallShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('on_call.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('on_call.manage');
    }

    public function update(User $user, OnCallShift $shift): bool
    {
        return $user->can('on_call.manage');
    }

    public function delete(User $user, OnCallShift $shift): bool
    {
        return $user->can('on_call.manage');
    }
}
