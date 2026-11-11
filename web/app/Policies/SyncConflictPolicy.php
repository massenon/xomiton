<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\User;

/**
 * Conflits de synchronisation (P13-01, ROLE_ET_PERMISSIONS.md §3) : l'administration (`sync.manage`) les consulte et les
 * tranche tous (valeur serveur, valeur de l'appareil ou fusion) ; le·la pair (`sync.resolve_own`, décision client du
 * 2026-10-29) tranche lui·elle-même les conflits **de ses appareils sur ses propres dossiers** (dossiers qu'il·elle a
 * collectés, CaseRecordPolicy::update), en choisissant la valeur du serveur ou celle de l'appareil — la fusion à la main
 * reste à l'administration.
 */
final class SyncConflictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sync.manage');
    }

    public function resolve(User $user, SyncConflict $conflict): bool
    {
        return $user->can('sync.manage') || $this->resolveOwn($user, $conflict);
    }

    /** Fusion saisie à la main : administration seulement. */
    public function merge(User $user, SyncConflict $conflict): bool
    {
        return $user->can('sync.manage');
    }

    private function resolveOwn(User $user, SyncConflict $conflict): bool
    {
        if (! $user->can('sync.resolve_own')) {
            return false;
        }

        $device = $conflict->device;
        $subject = $conflict->subject;

        return $device !== null
            && (int) $device->user_id === (int) $user->getKey()
            && $subject instanceof CaseRecord
            && $user->can('update', $subject);
    }
}
