<?php

declare(strict_types=1);

namespace App\Application\Account\RoleRequests;

use App\Application\Admin\Roles\ManagePeerRole;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Domain\Account\Exceptions\RoleRequestRefused;
use App\Models\RoleRequest;
use App\Models\User;
use App\Notifications\RoleNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Décision sur une demande de rôle pair (D-MOB-15) par un compte habilité (`peers.assign`, UserPolicy `assignPeerRole` :
 * jamais sa propre demande, donc aucune escalade par soi-même). Approuver donne le rôle via ManagePeerRole (la personne
 * reste membre, notification `peer_granted`, journal `roles.peer_granted via=role_request`) ; refuser exige un motif
 * écrit pour la personne (notification `peer_refused`) et laisse le compte inchangé. La décision est prise sous verrou :
 * une demande déjà traitée ne l'est jamais deux fois. Journal `roles.request_approved` / `roles.request_rejected`.
 */
final class ReviewRoleRequest
{
    public function __construct(private readonly ManagePeerRole $peers) {}

    /**
     * @throws RoleRequestRefused|AccountActionRefused
     */
    public function approve(RoleRequest $request, User $by): RoleRequest
    {
        $decided = $this->decide($request, $by, RoleRequest::STATUS_APPROVED, null, function (RoleRequest $locked) use ($by): void {
            $this->peers->apply($locked->user, $by, 'role_request');
        });

        Audit::log('roles.request_approved', $decided->user, sprintf('pseudo=%s', $decided->user->pseudo), $by);

        return $decided;
    }

    /**
     * @throws RoleRequestRefused
     */
    public function reject(RoleRequest $request, User $by, string $reason): RoleRequest
    {
        $reason = Str::limit(trim($reason), RoleRequest::REASON_MAX, '');

        if (mb_strlen($reason) < 3) {
            throw new AccountActionRefused('reason_required');
        }

        $decided = $this->decide($request, $by, RoleRequest::STATUS_REJECTED, $reason, null);

        $decided->user->notify(new RoleNotice(RoleNotice::PEER_REFUSED, $reason));
        Audit::log('roles.request_rejected', $decided->user, sprintf('pseudo=%s ; motif=%s', $decided->user->pseudo, $reason), $by);

        return $decided;
    }

    /**
     * @param  (callable(RoleRequest): void)|null  $grant
     */
    private function decide(RoleRequest $request, User $by, string $status, ?string $reason, ?callable $grant): RoleRequest
    {
        Gate::forUser($by)->authorize('assignPeerRole', $request->user);

        return DB::transaction(function () use ($request, $by, $status, $reason, $grant): RoleRequest {
            $locked = RoleRequest::query()->whereKey($request->getKey())->lockForUpdate()->with('user')->firstOrFail();

            if (! $locked->isPending()) {
                throw new RoleRequestRefused('already_decided');
            }

            if ($grant !== null) {
                $grant($locked);
            }

            $locked->forceFill([
                'status' => $status,
                'open_user_id' => null,
                'decided_by' => $by->getKey(),
                'decided_at' => now(),
                'decision_reason' => $reason,
            ])->save();

            return $locked;
        });
    }
}
