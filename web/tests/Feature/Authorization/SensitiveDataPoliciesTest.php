<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Policies\Scopes\AlertScope;
use App\Policies\Scopes\CaseRecordScope;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * Cloisonnement des données sensibles (ROLE_ET_PERMISSIONS.md §2, §5, §6) : un pair ne voit que ses dossiers
 * et les alertes routées vers lui·elle ; l'administration voit tout.
 */

beforeEach(function (): void {
    RoleUsers::seedRoles();
    $this->users = RoleUsers::all();
});

describe('CaseRecordPolicy', function (): void {
    it('ouvre la collecte aux pair·es et à l’administration', function (): void {
        RoleUsers::assertAbility($this->users, 'viewAny', CaseRecord::class, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'create', CaseRecord::class, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'export', CaseRecord::class, ['admin', 'superadmin']);
    });

    it('laisse un pair voir et modifier ses dossiers, pas ceux d’un autre pair', function (): void {
        $otherPeer = RoleUsers::make('pair');
        $mine = fn (User $me): CaseRecord => CaseRecord::factory()->collectedBy($me)->create();
        $theirs = CaseRecord::factory()->collectedBy($otherPeer)->create();

        RoleUsers::assertAbility($this->users, 'view', $mine, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $mine, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'close', $mine, ['pair', 'admin', 'superadmin']);

        RoleUsers::assertAbility($this->users, 'view', $theirs, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $theirs, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $theirs, ['admin', 'superadmin']);
    });

    it('applique le même cloisonnement au scope visibleTo', function (): void {
        $peer = $this->users['pair'];
        $otherPeer = RoleUsers::make('pair');
        $mine = CaseRecord::factory()->collectedBy($peer)->create();
        // Deux créations séparées : le code séquentiel (RG-06) est calculé avant l'insertion, count(2) le dupliquerait.
        CaseRecord::factory()->collectedBy($otherPeer)->create();
        CaseRecord::factory()->collectedBy($otherPeer)->create();

        expect(CaseRecordScope::visibleTo($peer)->pluck('id')->all())->toBe([$mine->id])
            ->and(CaseRecordScope::visibleTo($this->users['admin'])->count())->toBe(3)
            ->and(CaseRecordScope::visibleTo($this->users['superadmin'])->count())->toBe(3)
            ->and(CaseRecordScope::visibleTo($this->users['membre'])->count())->toBe(0)
            ->and(CaseRecordScope::visibleTo($this->users['moderateur'])->count())->toBe(0)
            ->and(app(App\Policies\CaseRecordPolicy::class)->scope($peer)->count())->toBe(1);
    });
});

describe('CaseFollowupPolicy', function (): void {
    it('suit les droits du dossier parent', function (): void {
        $otherPeer = RoleUsers::make('pair');
        $theirCase = CaseRecord::factory()->collectedBy($otherPeer)->create();
        $theirFollowup = CaseFollowup::factory()->forCase($theirCase)->create();

        $mineFollowup = fn (User $me): CaseFollowup => CaseFollowup::factory()
            ->forCase(CaseRecord::factory()->collectedBy($me)->create())
            ->create();
        $mineCase = fn (User $me): array => [CaseFollowup::class, CaseRecord::factory()->collectedBy($me)->create()];

        RoleUsers::assertAbility($this->users, 'viewAny', CaseFollowup::class, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $mineFollowup, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $mineFollowup, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'create', $mineCase, ['pair', 'admin', 'superadmin']);

        RoleUsers::assertAbility($this->users, 'view', $theirFollowup, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $theirFollowup, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'create', [CaseFollowup::class, $theirCase], ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $theirFollowup, ['admin', 'superadmin']);
    });
});

describe('AlertPolicy', function (): void {
    it('ouvre le tableau des alertes aux pair·es et à l’administration', function (): void {
        RoleUsers::assertAbility($this->users, 'viewAny', Alert::class, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'export', Alert::class, ['admin', 'superadmin']);
    });

    it('laisse un pair prendre et clore ses alertes routées, sans réattribuer', function (): void {
        $routedToMe = function (User $me): Alert {
            $contact = DirectoryContact::factory()->linkedTo($me)->create();

            return Alert::factory()->routedTo($contact)->create();
        };

        RoleUsers::assertAbility($this->users, 'view', $routedToMe, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'take', $routedToMe, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'close', $routedToMe, ['pair', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'reassign', $routedToMe, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'note', $routedToMe, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'escalate', $routedToMe, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $routedToMe, ['admin', 'superadmin']);
    });

    it('cache à un pair l’alerte routée vers un autre pair ou non routée', function (): void {
        $otherContact = DirectoryContact::factory()->linkedTo(RoleUsers::make('pair'))->create();
        $theirs = Alert::factory()->routedTo($otherContact)->create();
        $unrouted = Alert::factory()->create();

        RoleUsers::assertAbility($this->users, 'view', $theirs, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'take', $theirs, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'close', $theirs, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $unrouted, ['admin', 'superadmin']);
    });

    it('applique le même cloisonnement au scope routedTo', function (): void {
        $peer = $this->users['pair'];
        $mine = Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo($peer)->create())->create();
        Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo(RoleUsers::make('pair'))->create())->create();
        Alert::factory()->create();

        expect(AlertScope::routedTo($peer)->pluck('id')->all())->toBe([$mine->id])
            ->and(AlertScope::routedTo($this->users['admin'])->count())->toBe(3)
            ->and(AlertScope::routedTo($this->users['membre'])->count())->toBe(0)
            ->and(AlertScope::routedTo($this->users['moderateur'])->count())->toBe(0)
            ->and(AlertScope::isRoutedTo($mine, $peer))->toBeTrue()
            ->and(app(App\Policies\AlertPolicy::class)->scope($peer)->count())->toBe(1);
    });
});

describe('DirectoryContactPolicy', function (): void {
    it('montre les contacts publics aux membres et réserve la gestion à l’administration', function (): void {
        $public = DirectoryContact::factory()->create();
        $hidden = DirectoryContact::factory()->hidden()->create();
        $inactive = DirectoryContact::factory()->inactive()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', DirectoryContact::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $public, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $hidden, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $inactive, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', fn (User $me): DirectoryContact => DirectoryContact::factory()->hidden()->linkedTo($me)->create(), RoleUsers::ROLES);

        foreach (['create', 'import', 'export'] as $ability) {
            RoleUsers::assertAbility($this->users, $ability, DirectoryContact::class, ['admin', 'superadmin']);
        }
        RoleUsers::assertAbility($this->users, 'update', $public, ['admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $public, ['admin', 'superadmin']);
    });
});
