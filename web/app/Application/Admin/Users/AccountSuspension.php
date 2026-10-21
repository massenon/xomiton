<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Auth\ConnectedDevices;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\ModerationNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Suspension et réactivation d'un compte par l'équipe (P12-01, P12-06, P12-09 ; UserPolicy `suspend` / `reactivate`).
 *
 * Suspendre : `users.suspend` (modérateur·rice comprise), jamais soi-même ni — pour qui n'a pas `roles.assign_admin` —
 * un compte d'administration (RG-15, UserPolicy) ; un compte « en attente » se valide ou se rejette d'abord (une
 * réactivation le ferait passer actif sans validation, RG-03). Motif obligatoire (≤ 200 caractères, écrit pour la
 * personne), accès fermés tout de suite (jetons Sanctum et sessions web), notification et journal `users.suspended`.
 * Réactiver : réservé à `users.manage` (la vue du modérateur ne propose que « Suspendre », ROLE_ET_PERMISSIONS §3).
 */
final class AccountSuspension
{
    public const REASON_MAX = 200;

    public function __construct(private readonly ConnectedDevices $devices) {}

    /**
     * @throws AccountActionRefused
     */
    public function suspend(User $target, User $by, string $reason): void
    {
        if ($by->is($target)) {
            throw new AccountActionRefused('own_suspend');
        }

        Gate::forUser($by)->authorize('suspend', $target);

        $reason = trim($reason);

        if ($reason === '') {
            throw new AccountActionRefused('reason_required');
        }

        if ($target->status === UserStatus::Pending) {
            throw new AccountActionRefused('pending_account');
        }

        if ($target->status === UserStatus::Suspended) {
            return;
        }

        $reason = Str::limit($reason, self::REASON_MAX, '');

        $target->forceFill([
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();

        // Plus aucun accès : jetons Sanctum et sessions web fermés (revokeOthers sans exception = tous).
        $this->devices->revokeOthers($target);

        $target->notify(new ModerationNotice(ModerationNotice::SUSPENDED, $reason));
        Audit::log('users.suspended', $target, sprintf('pseudo=%s ; motif=%s', $target->pseudo, $reason), $by);
    }

    /**
     * @throws AccountActionRefused
     */
    public function reactivate(User $target, User $by): void
    {
        Gate::forUser($by)->authorize('reactivate', $target);
        Gate::forUser($by)->authorize('update', $target);

        if ($target->status !== UserStatus::Suspended) {
            return;
        }

        $target->forceFill([
            'status' => UserStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        $target->notify(new ModerationNotice(ModerationNotice::REACTIVATED));
        Audit::log('users.reactivated', $target, sprintf('pseudo=%s', $target->pseudo), $by);
    }
}
