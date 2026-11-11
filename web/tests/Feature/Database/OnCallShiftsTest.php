<?php

declare(strict_types=1);

use App\Application\Alerts\OnCallRoster;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/*
 * P1-17 : table `on_call_shifts`, modèle OnCallShift et référentiel de la garde OnCallRoster — un SOS à 3 h du matin
 * est confié à la personne de garde ; sans garde, repli sur les admins habilités ; point focal national et de zone.
 */

beforeEach(function (): void {
    // 3 h du matin : seule la garde de nuit est en cours.
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3, 0));
});

describe('on_call_shifts', function (): void {
    it('crée une plage de garde et la retrouve par ses portées', function (): void {
        $night = OnCallShift::factory()->night()->withNotes('Garde de nuit')->create();
        $past = OnCallShift::factory()->past()->create();
        $upcoming = OnCallShift::factory()->upcoming()->create();
        $zone = Zone::factory()->create();
        $focal = OnCallShift::factory()->focal($zone)->create();

        expect(Schema::hasTable('on_call_shifts'))->toBeTrue()
            ->and($night->starts_at->format('Y-m-d H:i'))->toBe('2026-10-16 22:00')
            ->and($night->ends_at->format('Y-m-d H:i'))->toBe('2026-10-17 06:00')
            ->and($night->kind)->toBe(OnCallShift::KIND_ADMIN)
            ->and($night->isAdmin())->toBeTrue()
            ->and($night->isNational())->toBeTrue()
            ->and($night->isActive())->toBeTrue()
            ->and($night->isActive(Carbon::create(2026, 10, 17, 6, 0)))->toBeFalse()
            ->and($night->notes)->toBe('Garde de nuit')
            ->and($night->user)->toBeInstanceOf(User::class)
            ->and($focal->isFocal())->toBeTrue()
            ->and($focal->zone->is($zone))->toBeTrue()
            ->and(OnCallShift::query()->active()->pluck('id')->all())->toEqualCanonicalizing([$night->id, $focal->id])
            ->and(OnCallShift::query()->active()->forKind(OnCallShift::KIND_ADMIN)->pluck('id')->all())->toBe([$night->id])
            ->and(OnCallShift::query()->forZone($zone)->pluck('id')->all())->toBe([$focal->id])
            ->and(OnCallShift::query()->forZone(null)->count())->toBe(3)
            ->and(OnCallShift::query()->upcoming()->pluck('id')->all())->toBe([$upcoming->id])
            ->and(OnCallShift::query()->chronological()->first()?->id)->toBe($past->id);
    });

    it('suit la suppression définitive du compte', function (): void {
        $shift = OnCallShift::factory()->create();

        $shift->user->forceDelete();

        expect(OnCallShift::query()->whereKey($shift->id)->exists())->toBeFalse();
    });
});

describe('OnCallRoster', function (): void {
    it('confie un SOS à 3 h du matin à la personne de garde, et non aux autres admins', function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);

        $onCall = User::factory()->create();
        $onCall->assignRole('admin');
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('admin');
        $suspended = User::factory()->suspended()->create();
        $suspended->assignRole('admin');

        OnCallShift::factory()->forUser($onCall)->night()->create();
        OnCallShift::factory()->forUser($suspended)->night()->create();
        OnCallShift::factory()->forUser($otherAdmin)->upcoming()->create();

        $roster = app(OnCallRoster::class);

        expect($roster->hasAdminOnCall())->toBeTrue()
            ->and($roster->adminsOnCall()->pluck('id')->all())->toBe([$onCall->id])
            ->and($roster->adminsOnCall(Carbon::create(2026, 10, 17, 3, 0))->pluck('id')->all())->toBe([$onCall->id]);

        // À 7 h, plus personne de garde : repli sur les admins habilités (le compte suspendu reste exclu).
        $sevenAm = Carbon::create(2026, 10, 17, 7, 0);

        expect($roster->hasAdminOnCall($sevenAm))->toBeFalse()
            ->and($roster->adminsOnCall($sevenAm)->pluck('id')->all())->toEqualCanonicalizing([$onCall->id, $otherAdmin->id]);
    });

    it('sans garde planifiée, se replie sur les admins et superadmins détenant alerts.take', function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $peer = User::factory()->create();
        $peer->assignRole('pair');
        $moderator = User::factory()->create();
        $moderator->assignRole('moderateur');
        $suspended = User::factory()->suspended()->create();
        $suspended->assignRole('admin');
        $deleted = User::factory()->create();
        $deleted->assignRole('admin');
        $deleted->delete();

        $roster = app(OnCallRoster::class);

        expect($peer->can('alerts.take'))->toBeTrue()
            ->and($roster->adminsOnCall()->pluck('id')->all())->toEqualCanonicalizing([$admin->id, $superadmin->id]);
    });

    it('ne renvoie personne quand les rôles ne sont pas encore créés', function (): void {
        User::factory()->create();

        expect(app(OnCallRoster::class)->adminsOnCall())->toBeEmpty();
    });

    it('désigne le point focal national et celui de la zone, ou leur remplaçant·e de garde', function (): void {
        $zone = Zone::factory()->create();
        $otherZone = Zone::factory()->create();

        DirectoryContact::factory()->focalNational()->inactive()->create();
        $national = DirectoryContact::factory()->focalNational()->create();
        $zoneFocal = DirectoryContact::factory()->focalZone($zone)->create();
        DirectoryContact::factory()->focalZone($zone)->inactive()->create();
        DirectoryContact::factory()->focalZone($otherZone)->create();

        $roster = app(OnCallRoster::class);

        expect($roster->nationalFocal()?->id)->toBe($national->id)
            ->and($roster->zoneFocal($zone)?->id)->toBe($zoneFocal->id)
            ->and($roster->zoneFocal(Zone::factory()->create()))->toBeNull();

        // Remplaçant·es de garde : une plage `focal` nationale et une plage `focal` de zone, comptes liés à l'annuaire.
        $replacement = User::factory()->create();
        $replacementContact = DirectoryContact::factory()->linkedTo($replacement)->create();
        OnCallShift::factory()->forUser($replacement)->focal()->night()->create();

        $zoneReplacement = User::factory()->create();
        $zoneReplacementContact = DirectoryContact::factory()->linkedTo($zoneReplacement)->create();
        OnCallShift::factory()->forUser($zoneReplacement)->focal($zone)->night()->create();

        // Une plage `focal` sans fiche d'annuaire liée ne désigne personne.
        OnCallShift::factory()->focal($otherZone)->night()->create();

        expect($roster->nationalFocal()?->id)->toBe($replacementContact->id)
            ->and($roster->zoneFocal($zone)?->id)->toBe($zoneReplacementContact->id)
            ->and($roster->zoneFocal($otherZone)?->is_national_focal)->toBeFalse()
            ->and($roster->nationalFocal(Carbon::create(2026, 10, 17, 7, 0))?->id)->toBe($national->id)
            ->and($roster->zoneFocal($zone, Carbon::create(2026, 10, 17, 7, 0))?->id)->toBe($zoneFocal->id);
    });

    it('ne renvoie aucun point focal sans fiche active', function (): void {
        DirectoryContact::factory()->focalNational()->inactive()->create();

        $roster = app(OnCallRoster::class);

        expect($roster->nationalFocal())->toBeNull()
            ->and($roster->zoneFocal(Zone::factory()->create()))->toBeNull();
    });
});
