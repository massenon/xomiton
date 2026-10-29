<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Sync\Admin\RequestDeviceWipe;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Models\User;
use App\Notifications\RoleNotice;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Changement de rôle d'un compte (P12-01, RG-15) : on ne change jamais son propre rôle ; membre et pair demandent
 * `users.manage` (UserPolicy `assignRole`) ; modérateur·rice, administrateur·rice et super-admin demandent en plus
 * `roles.assign_admin` (`assignAdminRole`), tout comme le retrait d'un de ces rôles (`mayActOn`) ; le dernier super-admin
 * ne peut pas être rétrogradé (jamais d'administration sans superadmin). Rôles **cumulables** (un seul compte, décisions
 * client du 2026-10-29) : le rôle `membre` est toujours conservé (réseau social), le rôle `pair` reste quand on devient
 * modérateur·rice / admin (ses dossiers lui restent) et ne tombe que si « membre » est choisi ; le niveau choisi
 * (`pair`, `moderateur`, `admin`, `superadmin`) remplace l'ancien niveau d'administration. Le profil suit (pair ↔ membre).
 * Journal `users.role_changed` (ancien → nouveau) et notification de l'intéressé·e (RoleNotice).
 */
final class ChangeUserRole
{
    /**
     * @throws AccountActionRefused
     */
    public function __invoke(User $target, string $role, User $by): void
    {
        if (! in_array($role, AdminRoles::ALL, true)) {
            throw new AccountActionRefused('invalid_role');
        }

        if ($by->is($target)) {
            throw new AccountActionRefused('own_role');
        }

        $gate = Gate::forUser($by);
        $gate->authorize('assignRole', $target);

        if (AdminRoles::isAdministration($role)) {
            $gate->authorize('assignAdminRole', $target);
        }

        $current = AdminRoles::of($target);

        if ($current === $role) {
            return;
        }

        $before = $target->getRoleNames()->all();

        DB::transaction(function () use ($target, $role, $current): void {
            // Verrou sur les lignes des super-admin : deux rétrogradations simultanées ne vident pas le rôle.
            if ($current === AdminRoles::SUPERADMIN && $this->superadminCount() <= 1) {
                throw new AccountActionRefused('last_superadmin');
            }

            $target->syncRoles(array_map(
                static fn (string $name): Role => Role::findOrCreate($name, RolesAndPermissionsSeeder::GUARD),
                self::rolesFor($target, $role),
            ));

            $profile = $target->profile;

            if ($profile !== null) {
                if ($target->hasRole('pair')) {
                    $profile->forceFill(['member_type' => MemberType::Peer])->save();
                } elseif ($profile->member_type === MemberType::Peer) {
                    $profile->forceFill(['member_type' => MemberType::Member])->save();
                }
            }
        });

        Audit::log('users.role_changed', $target, sprintf('pseudo=%s ; ancien=%s ; nouveau=%s', $target->pseudo, $current, $role), $by);

        // P15-11 : plus de rôle pair → les appareils de la personne vident les données de l'espace pair.
        if (in_array('pair', $before, true) && ! $target->hasRole('pair')) {
            app(RequestDeviceWipe::class)->forAccount($target, RequestDeviceWipe::REASON_PEER_ROLE_REMOVED, $by);
        }

        $this->notify($target, $before, $target->fresh()?->getRoleNames()->all() ?? []);
    }

    /**
     * Rôles du compte après le changement : `membre` toujours, `pair` conservé pour un niveau d'administration.
     *
     * @return list<string>
     */
    public static function rolesFor(User $target, string $level): array
    {
        return match (true) {
            $level === 'membre' => ['membre'],
            $level === 'pair' => ['membre', 'pair'],
            default => array_values(array_filter(['membre', $level, $target->hasRole('pair') ? 'pair' : null])),
        };
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    private function notify(User $target, array $before, array $after): void
    {
        $pair = [in_array('pair', $before, true), in_array('pair', $after, true)];
        $admin = [array_intersect(AdminRoles::ADMINISTRATION, $before) !== [], array_intersect(AdminRoles::ADMINISTRATION, $after) !== []];

        $kinds = array_filter([
            $pair[1] && ! $pair[0] ? RoleNotice::PEER_GRANTED : null,
            $pair[0] && ! $pair[1] ? RoleNotice::PEER_REVOKED : null,
            $admin[1] && ! $admin[0] ? RoleNotice::ADMIN_GRANTED : null,
            $admin[0] && ! $admin[1] ? RoleNotice::ADMIN_REVOKED : null,
        ]);

        foreach ($kinds as $kind) {
            $target->notify(new RoleNotice($kind));
        }
    }

    private function superadminCount(): int
    {
        return User::query()->role(AdminRoles::SUPERADMIN)->lockForUpdate()->count();
    }
}
