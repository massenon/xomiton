<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * Gate::before : le superadmin détient tout (ROLE_ET_PERMISSIONS.md §3), sauf la modification du journal d'audit.
 */

beforeEach(fn () => RoleUsers::seedRoles());

it('accorde toutes les permissions et toutes les policies au superadmin', function (): void {
    $super = RoleUsers::make('superadmin');

    expect($super->can('roles.assign_admin'))->toBeTrue()
        ->and($super->can('permission.inexistante'))->toBeTrue()
        ->and($super->can('deleteSystem', EmailTemplate::factory()->system()->create()))->toBeTrue()
        ->and($super->can('restore', Backup::factory()->create()))->toBeTrue()
        ->and($super->can('update', Setting::factory()->create()))->toBeTrue()
        ->and($super->can('purge', AuditLog::class))->toBeTrue()
        ->and($super->can('viewAny', AuditLog::class))->toBeTrue();
});

it('ne fait pas du superadmin un éditeur du journal d’audit', function (): void {
    $super = RoleUsers::make('superadmin');
    $log = AuditLog::factory()->create();

    expect($super->can('update', $log))->toBeFalse()
        ->and($super->can('delete', $log))->toBeFalse()
        ->and($super->can('create', AuditLog::class))->toBeFalse();
});

it('laisse les autres rôles aux policies et permissions', function (string $role): void {
    $user = RoleUsers::make($role);

    expect($user->can('permission.inexistante'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('roles.assign_admin'))->toBeFalse()
        ->and($user->can('update', AuditLog::factory()->create()))->toBeFalse();
})->with(RoleUsers::except(['superadmin']));

it('ne révèle rien à un compte sans rôle ni à un visiteur', function (): void {
    $noRole = User::factory()->create();

    expect($noRole->can('viewAny', AuditLog::class))->toBeFalse()
        ->and($noRole->can('social.access'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewAny', AuditLog::class))->toBeFalse();
});
