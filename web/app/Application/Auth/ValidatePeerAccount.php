<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Notifications\PeerAccountValidated;
use App\Domain\Auth\Events\PeerValidated;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Validation d'un compte pair éducateur·rice par l'administration (P2-10, RG-03, FR-AUTH-05) : statut actif,
 * rôle `pair` garanti, puis une seule notification PeerAccountValidated (cloche + e-mail `compte-valide` par template,
 * P3-02) et journal d'audit au nom de l'admin. L'autorisation (`users.validate_peers`) est vérifiée par l'appelant (policy).
 */
final class ValidatePeerAccount
{
    public function __invoke(User $peer, User $by): void
    {
        DB::transaction(function () use ($peer): void {
            $peer->forceFill(['status' => UserStatus::Active])->save();

            if ($peer->profile !== null && $peer->profile->member_type !== MemberType::Peer) {
                $peer->profile->forceFill(['member_type' => MemberType::Peer])->save();
            }

            // Rôles cumulables : la personne reste membre et gagne l'espace pair.
            $peer->assignRole(Role::findOrCreate('membre', RolesAndPermissionsSeeder::GUARD));

            if (! $peer->hasRole(CompleteRegistrationProfile::PEER_ROLE)) {
                $peer->assignRole(Role::findOrCreate(CompleteRegistrationProfile::PEER_ROLE, RolesAndPermissionsSeeder::GUARD));
            }
        });

        $peer->notify(new PeerAccountValidated($by));

        Audit::log('users.validate_peer', $peer, sprintf('pseudo=%s', $peer->pseudo), $by);
        PeerValidated::dispatch((int) $peer->getKey(), (int) $by->getKey());
    }
}
