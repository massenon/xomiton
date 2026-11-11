<?php

declare(strict_types=1);

namespace App\Application\Admin\Roles;

use App\Application\Sync\Admin\RequestDeviceWipe;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\RoleNotice;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Attribuer ou retirer le rôle `pair` à un compte existant (décisions client du 2026-10-29, 3a) : la personne **reste
 * membre** (réseau social conservé) et gagne l'espace pair. Permission `peers.assign` (UserPolicy `assignPeerRole`,
 * `revokePeerRole`) ; on ne change jamais son propre rôle ; seul un compte actif dont l'e-mail est confirmé reçoit le
 * rôle (un compte en attente passe par la validation des inscriptions). Journal `roles.peer_granted` /
 * `roles.peer_revoked` et notification de l'intéressé·e (RoleNotice). Idempotent : rendre `false` quand rien ne change.
 */
final class ManagePeerRole
{
    public const ROLE = 'pair';

    /**
     * @throws AccountActionRefused
     */
    public function grant(User $target, User $by, ?string $via = null): bool
    {
        Gate::forUser($by)->authorize('assignPeerRole', $target);

        return $this->apply($target, $by, $via);
    }

    /**
     * Attribution sans contrôle de permission : l'appelant a déjà autorisé l'acte (invitation validée, validation d'un
     * compte pair en attente par `users.validate_peers`).
     *
     * @throws AccountActionRefused
     */
    public function apply(User $target, User $by, ?string $via = null): bool
    {
        if ($target->status !== UserStatus::Active) {
            throw new AccountActionRefused('not_active');
        }

        if ($target->email_verified_at === null) {
            throw new AccountActionRefused('email_unverified');
        }

        if ($target->hasRole(self::ROLE)) {
            return false;
        }

        DB::transaction(function () use ($target): void {
            $target->assignRole(Role::findOrCreate('membre', RolesAndPermissionsSeeder::GUARD));
            $target->assignRole(Role::findOrCreate(self::ROLE, RolesAndPermissionsSeeder::GUARD));
            $target->profile?->forceFill(['member_type' => MemberType::Peer])->save();
        });

        Audit::log('roles.peer_granted', $target, sprintf('pseudo=%s%s', $target->pseudo, $via === null ? '' : ' ; via='.$via), $by);
        $target->notify(new RoleNotice(RoleNotice::PEER_GRANTED));

        return true;
    }

    /**
     * @throws AccountActionRefused
     */
    public function revoke(User $target, User $by, ?string $reason = null): bool
    {
        Gate::forUser($by)->authorize('revokePeerRole', $target);

        if (! $target->hasRole(self::ROLE)) {
            return false;
        }

        $reason = $reason === null ? '' : trim($reason);

        DB::transaction(function () use ($target): void {
            $target->removeRole(self::ROLE);

            if ($target->profile?->member_type === MemberType::Peer) {
                $target->profile->forceFill(['member_type' => MemberType::Member])->save();
            }
        });

        Audit::log('roles.peer_revoked', $target, sprintf('pseudo=%s%s', $target->pseudo, $reason === '' ? '' : ' ; motif='.$reason), $by);
        $target->notify(new RoleNotice(RoleNotice::PEER_REVOKED, $reason === '' ? null : $reason));
        // P15-11 : les appareils de la personne gardent des données de l'espace pair (dossiers, alertes) : ils sont effacés.
        app(RequestDeviceWipe::class)->forAccount($target, RequestDeviceWipe::REASON_PEER_ROLE_REMOVED, $by);

        return true;
    }
}
