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
 * Bannissement d'un compte (P18-06 ; Q97, appliqué par défaut : statut **définitif** `banni`, distinct de la suspension).
 *
 * Bannir : `users.ban` (admin, superadmin ; UserPolicy::ban), jamais soi-même ni un compte d'administration
 * (`admin.access`), motif obligatoire (≤ 200 caractères, écrit pour la personne). Le compte passe `banni` : connexion
 * refusée sur le web, l'API et l'application avec un message neutre (AccountUnavailable), sessions web et jetons
 * révoqués tout de suite, notification et journal `users.banned` (pseudo et motif, jamais de contenu). Un compte en
 * attente se rejette plutôt (RG-03) ; un compte suspendu peut être banni (la suspension est remplacée).
 * Lever : `users.unban`, réservé au superadmin (UserPolicy::unban) : compte de nouveau actif, journal `users.unbanned`.
 */
final class AccountBan
{
    public const REASON_MAX = 200;

    public function __construct(private readonly ConnectedDevices $devices) {}

    /**
     * @throws AccountActionRefused
     */
    public function ban(User $target, User $by, string $reason): void
    {
        if ($by->is($target)) {
            throw new AccountActionRefused('own_ban');
        }

        Gate::forUser($by)->authorize('ban', $target);

        if ($target->can('admin.access')) {
            throw new AccountActionRefused('admin_ban');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new AccountActionRefused('reason_required');
        }

        if ($target->isPending()) {
            throw new AccountActionRefused('pending_account');
        }

        if ($target->isBanned()) {
            return;
        }

        $reason = Str::limit($reason, self::REASON_MAX, '');

        $target->forceFill([
            'status' => UserStatus::Banned,
            'banned_at' => now(),
            'banned_by' => (int) $by->getKey(),
            'ban_reason' => $reason,
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_until' => null,
        ])->save();

        // Plus aucun accès : jetons Sanctum et sessions web fermés.
        $this->devices->revokeOthers($target);

        $target->notify(new ModerationNotice(ModerationNotice::BANNED, $reason));
        Audit::log('users.banned', $target, sprintf('pseudo=%s ; motif=%s', $target->pseudo, $reason), $by);
    }

    /**
     * @throws AccountActionRefused
     */
    public function unban(User $target, User $by): void
    {
        Gate::forUser($by)->authorize('unban', $target);

        if (! $target->isBanned()) {
            throw new AccountActionRefused('not_banned');
        }

        $target->forceFill([
            'status' => UserStatus::Active,
            'banned_at' => null,
            'banned_by' => null,
            'ban_reason' => null,
        ])->save();

        $target->notify(new ModerationNotice(ModerationNotice::REACTIVATED));
        Audit::log('users.unbanned', $target, sprintf('pseudo=%s', $target->pseudo), $by);
    }
}
