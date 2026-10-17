<?php

declare(strict_types=1);

use App\Models\User;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * UserPolicy (ROLE_ET_PERMISSIONS.md §3 « Utilisateurs », RG-15 : rôles d'administration réservés au superadmin).
 */

beforeEach(function (): void {
    RoleUsers::seedRoles();
    $this->users = RoleUsers::all();
});

it('réserve la liste des comptes à la modération et à l’administration', function (): void {
    RoleUsers::assertAbility($this->users, 'viewAny', User::class, ['moderateur', 'admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'create', User::class, ['admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'export', User::class, ['admin', 'superadmin']);
});

it('laisse chacun·e voir et modifier son propre compte', function (): void {
    RoleUsers::assertAbility($this->users, 'view', fn (User $me): User => $me, RoleUsers::ROLES);
    RoleUsers::assertAbility($this->users, 'update', fn (User $me): User => $me, RoleUsers::ROLES);

    $member = RoleUsers::make('membre');
    RoleUsers::assertAbility($this->users, 'view', $member, ['moderateur', 'admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'update', $member, ['admin', 'superadmin']);
});

it('permet de suspendre un membre mais jamais soi-même', function (): void {
    $member = RoleUsers::make('membre');

    RoleUsers::assertAbility($this->users, 'suspend', $member, ['moderateur', 'admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'reactivate', $member, ['moderateur', 'admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'suspend', fn (User $me): User => $me, []);
});

it('protège les comptes d’administration : seul le superadmin agit sur eux (RG-15)', function (): void {
    $admin = RoleUsers::make('admin');

    RoleUsers::assertAbility($this->users, 'suspend', $admin, ['superadmin']);
    RoleUsers::assertAbility($this->users, 'delete', $admin, ['superadmin']);
    RoleUsers::assertAbility($this->users, 'resetPassword', $admin, ['superadmin']);
    RoleUsers::assertAbility($this->users, 'assignRole', $admin, ['superadmin']);
});

it('réserve l’attribution des rôles d’administration au superadmin', function (): void {
    $member = RoleUsers::make('membre');

    RoleUsers::assertAbility($this->users, 'assignAdminRole', $member, ['superadmin']);
    RoleUsers::assertAbility($this->users, 'assignRole', $member, ['admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'delete', $member, ['admin', 'superadmin']);
    RoleUsers::assertAbility($this->users, 'assignAdminRole', fn (User $me): User => $me, []);
});

it('confie la validation des pair·es à l’administration (RG-03)', function (): void {
    $pending = User::factory()->pending()->peer()->create();
    $pending->syncRoles(['pair']);

    RoleUsers::assertAbility($this->users, 'validatePeer', $pending, ['admin', 'superadmin']);
});
