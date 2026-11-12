<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Auth\ConnectedDevices;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ModerationNotice;
use App\Support\Audit;
use App\Support\LocaleFormatter;
use Carbon\CarbonInterface;
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
 *
 * P18-06 : suspension **temporaire** avec date de fin (`suspended_until`, au plus MAX_DAYS jours, dans le futur) ; la
 * commande planifiée `accounts:lift-suspensions` (liftExpired()) réactive les comptes arrivés à échéance, notifie la
 * personne et journalise `users.suspension_lifted` (acteur : système). Sans date : jusqu'à réactivation par l'équipe.
 * Un compte banni (`banni`) ne se suspend ni ne se réactive ici (AccountBan).
 */
final class AccountSuspension
{
    public const REASON_MAX = 200;

    /** Durée maximale d'une suspension temporaire (jours). */
    public const MAX_DAYS = 365;

    public function __construct(private readonly ConnectedDevices $devices) {}

    /**
     * @throws AccountActionRefused
     */
    public function suspend(User $target, User $by, string $reason, ?CarbonInterface $until = null): void
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

        if ($target->isBanned()) {
            throw new AccountActionRefused('banned_account');
        }

        if ($until !== null && ($until->lessThanOrEqualTo(now()) || $until->greaterThan(now()->addDays(self::MAX_DAYS)))) {
            throw new AccountActionRefused('invalid_suspension_end', ['max' => self::MAX_DAYS]);
        }

        if ($target->status === UserStatus::Suspended) {
            return;
        }

        $reason = Str::limit($reason, self::REASON_MAX, '');

        $target->forceFill([
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'suspended_until' => $until,
        ])->save();

        // Plus aucun accès : jetons Sanctum et sessions web fermés (revokeOthers sans exception = tous).
        $this->devices->revokeOthers($target);

        $endLabel = $until === null ? null : LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'))->longDate($until);
        $target->notify(new ModerationNotice($until === null ? ModerationNotice::SUSPENDED : ModerationNotice::SUSPENDED_UNTIL, $reason, until: $endLabel));
        Audit::log('users.suspended', $target, sprintf('pseudo=%s ; motif=%s%s', $target->pseudo, $reason, $until === null ? '' : ' ; fin='.$until->toIso8601String()), $by);
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

        $this->lift($target);
        Audit::log('users.reactivated', $target, sprintf('pseudo=%s', $target->pseudo), $by);
    }

    /**
     * Levée automatique des suspensions temporaires arrivées à échéance (commande `accounts:lift-suspensions`).
     *
     * @return int comptes réactivés
     */
    public function liftExpired(): int
    {
        $lifted = 0;

        User::query()->suspended()->whereNotNull('suspended_until')->where('suspended_until', '<=', now())
            ->orderBy('id')->each(function (User $user) use (&$lifted): void {
                $this->lift($user);
                Audit::logWithoutRequest('users.suspension_lifted', $user, sprintf('pseudo=%s', $user->pseudo), AuditLog::ACTOR_SYSTEM);
                $lifted++;
            });

        return $lifted;
    }

    private function lift(User $target): void
    {
        $target->forceFill([
            'status' => UserStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_until' => null,
        ])->save();

        $target->notify(new ModerationNotice(ModerationNotice::REACTIVATED));
    }
}
