<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Account\RequestAccountDeletion;
use App\Application\Admin\Roles\GrantInvitedRole;
use App\Application\Auth\ValidatePeerAccount;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\RoleInvitation;
use App\Models\User;
use App\Notifications\ModerationNotice;
use App\Notifications\RoleNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Demandes de compte en attente (P2-10 livré, P12-01 et P12-02 ; RG-03, Q26) : approuver ou rejeter, `users.validate_peers`.
 *  - approuver une demande de pair·e : ValidatePeerAccount (statut actif, rôle pair, e-mail `compte-valide`, journal
 *    `users.validate_peer`) ; une autre demande (validation manuelle de tous, Q26) : compte actif, notification
 *    « inscription validée » et journal `users.registration_approved` ;
 *  - rejeter une inscription : suppression logique du compte (RequestAccountDeletion : accès fermés, e-mail
 *    `compte-supprime`, effacement des données après le délai légal), journal `users.registration_rejected` au nom de
 *    l'admin, avec le motif ;
 *  - refuser une **demande de pair** (compte qui demande le rôle, ou invité·e à l'équipe) : la personne reste membre
 *    (compte actif, jamais supprimé), la demande est close, notification RoleNotice avec le motif et journal
 *    `users.peer_refused`.
 * Un compte approuvé qui détient une invitation à l'équipe reçoit son rôle (GrantInvitedRole). Le motif (≤ 200
 * caractères) est écrit pour la personne.
 */
final class ReviewRegistration
{
    public function __construct(
        private readonly ValidatePeerAccount $validatePeer,
        private readonly RequestAccountDeletion $delete,
        private readonly GrantInvitedRole $invitedRole,
    ) {}

    /**
     * @throws AccountActionRefused
     */
    public function approve(User $account, User $by): void
    {
        $this->guard($account, $by);

        if ($account->profile?->member_type === MemberType::Peer) {
            ($this->validatePeer)($account, $by);

            return;
        }

        $account->forceFill(['status' => UserStatus::Active])->save();
        $account->notify(new ModerationNotice(ModerationNotice::APPROVED));
        Audit::log('users.registration_approved', $account, sprintf('pseudo=%s', $account->pseudo), $by);
        ($this->invitedRole)($account);
    }

    /** La demande en attente vise-t-elle le rôle pair (profil « pair » ou invitation à l'équipe utilisée) ? */
    public static function isPeerRequest(User $account): bool
    {
        return $account->profile?->member_type === MemberType::Peer
            || RoleInvitation::query()->where('user_id', $account->getKey())->waitingValidation()->where('role', 'pair')->exists();
    }

    /**
     * @throws AccountActionRefused
     */
    public function reject(User $account, User $by, ?string $reason = null): void
    {
        $this->guard($account, $by);

        $reason = $reason === null ? '' : Str::limit(trim($reason), 200, '');

        if (self::isPeerRequest($account)) {
            $this->refusePeerRequest($account, $by, $reason);

            return;
        }

        ($this->delete)($account);

        Audit::log('users.registration_rejected', $account, sprintf('pseudo=%s%s', $account->pseudo, $reason === '' ? '' : ' ; motif='.$reason), $by);
    }

    private function refusePeerRequest(User $account, User $by, string $reason): void
    {
        DB::transaction(function () use ($account, $reason, $by): void {
            $account->forceFill(['status' => UserStatus::Active])->save();
            $account->removeRole('pair');
            $account->profile?->forceFill(['member_type' => MemberType::Member])->save();

            RoleInvitation::query()->where('user_id', $account->getKey())->waitingValidation()->update([
                'status' => RoleInvitation::STATUS_REFUSED,
                'decided_at' => now(),
                'decided_by' => $by->getKey(),
                'decision_reason' => $reason === '' ? null : $reason,
            ]);
        });

        $account->notify(new RoleNotice(RoleNotice::PEER_REFUSED, $reason === '' ? null : $reason));
        Audit::log('users.peer_refused', $account, sprintf('pseudo=%s%s', $account->pseudo, $reason === '' ? '' : ' ; motif='.$reason), $by);
    }

    private function guard(User $account, User $by): void
    {
        Gate::forUser($by)->authorize('validatePeer', $account);

        if ($account->status !== UserStatus::Pending) {
            throw new AccountActionRefused('not_pending');
        }
    }
}
