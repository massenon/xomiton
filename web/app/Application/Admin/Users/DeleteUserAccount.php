<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Account\RequestAccountDeletion;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Suppression d'un compte par l'équipe (P12-01) : `users.manage`, jamais soi-même, un compte d'administration seulement
 * avec `roles.assign_admin` (UserPolicy `delete`, RG-15), jamais le dernier super-admin. Suppression logique comme celle
 * que la personne peut demander (RequestAccountDeletion : accès fermés, e-mail `compte-supprime`, effacement des données
 * après le délai) ; journal `users.deleted_by_admin` au nom de l'admin.
 */
final class DeleteUserAccount
{
    public function __construct(private readonly RequestAccountDeletion $delete) {}

    /**
     * @throws AccountActionRefused
     */
    public function __invoke(User $target, User $by): void
    {
        if ($by->is($target)) {
            throw new AccountActionRefused('own_delete');
        }

        Gate::forUser($by)->authorize('delete', $target);

        if (AdminRoles::of($target) === AdminRoles::SUPERADMIN && User::query()->role(AdminRoles::SUPERADMIN)->count() <= 1) {
            throw new AccountActionRefused('last_superadmin');
        }

        ($this->delete)($target);

        Audit::log('users.deleted_by_admin', $target, sprintf('pseudo=%s', $target->pseudo), $by);
    }
}
