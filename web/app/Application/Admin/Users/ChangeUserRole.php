<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Changement de rôle d'un compte (P12-01, RG-15) : on ne change jamais son propre rôle ; membre et pair demandent
 * `users.manage` (UserPolicy `assignRole`) ; modérateur·rice, administrateur·rice et super-admin demandent en plus
 * `roles.assign_admin` (`assignAdminRole`), tout comme le retrait d'un de ces rôles (`mayActOn`) ; le dernier super-admin
 * ne peut pas être rétrogradé (jamais d'administration sans superadmin). Un seul rôle par compte ; le profil suit
 * (pair ↔ membre). Journal `users.role_changed` (ancien → nouveau).
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

        DB::transaction(function () use ($target, $role, $current): void {
            // Verrou sur les lignes des super-admin : deux rétrogradations simultanées ne vident pas le rôle.
            if ($current === AdminRoles::SUPERADMIN && $this->superadminCount() <= 1) {
                throw new AccountActionRefused('last_superadmin');
            }

            $target->syncRoles([Role::findOrCreate($role, RolesAndPermissionsSeeder::GUARD)]);

            $profile = $target->profile;

            if ($profile !== null && in_array($role, ['pair', 'membre'], true)) {
                $profile->forceFill(['member_type' => $role === 'pair' ? MemberType::Peer : MemberType::Member])->save();
            }
        });

        Audit::log('users.role_changed', $target, sprintf('pseudo=%s ; ancien=%s ; nouveau=%s', $target->pseudo, $current, $role), $by);
    }

    private function superadminCount(): int
    {
        return User::query()->role(AdminRoles::SUPERADMIN)->lockForUpdate()->count();
    }
}
