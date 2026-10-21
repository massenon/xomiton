<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Account\RequestAccountDeletion;
use App\Application\Auth\ValidatePeerAccount;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\ModerationNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Demandes de compte en attente (P2-10 livré, P12-01 et P12-02 ; RG-03, Q26) : approuver ou rejeter, `users.validate_peers`.
 *  - approuver une demande de pair·e : ValidatePeerAccount (statut actif, rôle pair, e-mail `compte-valide`, journal
 *    `users.validate_peer`) ; une autre demande (validation manuelle de tous, Q26) : compte actif, notification
 *    « inscription validée » et journal `users.registration_approved` ;
 *  - rejeter : suppression logique du compte (RequestAccountDeletion : accès fermés, e-mail `compte-supprime`,
 *    effacement des données après le délai légal), journal `users.registration_rejected` au nom de l'admin (plan Q91 :
 *    le refus notifié par un e-mail dédié viendra avec les modèles de P14).
 */
final class ReviewRegistration
{
    public function __construct(
        private readonly ValidatePeerAccount $validatePeer,
        private readonly RequestAccountDeletion $delete,
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
    }

    /**
     * @throws AccountActionRefused
     */
    public function reject(User $account, User $by, ?string $reason = null): void
    {
        $this->guard($account, $by);

        ($this->delete)($account);

        $reason = $reason === null ? '' : trim($reason);
        Audit::log('users.registration_rejected', $account, sprintf('pseudo=%s%s', $account->pseudo, $reason === '' ? '' : ' ; motif='.$reason), $by);
    }

    private function guard(User $account, User $by): void
    {
        Gate::forUser($by)->authorize('validatePeer', $account);

        if ($account->status !== UserStatus::Pending) {
            throw new AccountActionRefused('not_pending');
        }
    }
}
