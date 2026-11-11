<?php

declare(strict_types=1);

use App\Application\Platform\CancelMaintenanceWindow;
use App\Application\Platform\DisableMaintenance;
use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenanceOverview;
use App\Application\Platform\MaintenancePage;
use App\Application\Platform\MaintenanceStatus;
use App\Application\Platform\ScheduleMaintenance;
use App\Domain\Platform\Exceptions\MaintenanceAlreadyActive;
use App\Domain\Platform\Exceptions\MaintenanceLockout;
use App\Domain\Platform\Exceptions\MaintenanceWindowClosed;
use App\Domain\Platform\Exceptions\MaintenanceWindowInvalid;
use App\Domain\Platform\Exceptions\MaintenanceWindowOverlaps;
use App\Enums\MaintenanceState;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Livewire\Admin\Maintenance\Index;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * P13-06 : écran d'administration du mode maintenance (/admin/maintenance, Livewire, `platform.maintenance`) —
 * activation forte (motif obligatoire, confirmation) et désactivation journalisées, planification (fenêtre ouverte et
 * close seule, chevauchement 422, annonce préalable), annulation, message public, liste blanche RG-01 en lecture seule,
 * garde-fous anti-verrouillage et commande de secours `xomiton:maintenance on|off|status`.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
});

function maintenanceAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create(['pseudo' => ucfirst($role).'_'.fake()->unique()->numberBetween(1, 9999)]);
    $user->syncRoles([$role]);

    return $user;
}

describe('accès', function (): void {
    it('ouvre l’écran à admin et superadmin, avec l’entrée du menu', function (string $role): void {
        $this->actingAs(maintenanceAdmin($role))->get(route('admin.maintenance'))
            ->assertOk()
            ->assertSeeLivewire(Index::class)
            ->assertSee('Mode maintenance')
            ->assertSee('La plateforme est ouverte')
            ->assertSee('Toujours accessible (RG-01)')
            ->assertSee('php artisan xomiton:maintenance on', false)
            ->assertSee('href="'.route('admin.maintenance').'"', false);
    })->with(['admin', 'superadmin']);

    it('migre maintenance.manage vers platform.maintenance sur une base déjà semée (admin et superadmin, sans ligne orpheline)', function (): void {
        Permission::query()->where('name', 'platform.maintenance')->delete();
        Role::findByName('superadmin', 'web')->givePermissionTo(Permission::findOrCreate('maintenance.manage', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = maintenanceAdmin();
        $this->actingAs($admin)->get(route('admin.maintenance'))->assertForbidden();

        (require database_path('migrations/2026_11_05_090100_rename_maintenance_manage_permission.php'))->up();

        expect(Permission::query()->where('name', 'maintenance.manage')->exists())->toBeFalse()
            ->and(Role::findByName('admin', 'web')->hasPermissionTo('platform.maintenance'))->toBeTrue()
            ->and(Role::findByName('superadmin', 'web')->hasPermissionTo('platform.maintenance'))->toBeTrue()
            ->and(Role::findByName('moderateur', 'web')->hasPermissionTo('platform.maintenance'))->toBeFalse();
        $this->actingAs($admin->fresh())->get(route('admin.maintenance'))->assertOk();
    });

    it('refuse l’écran à un·e membre (403) et à la modération (403, entrée du menu masquée)', function (): void {
        $this->actingAs(maintenanceAdmin('membre'))->get(route('admin.maintenance'))->assertForbidden();

        $moderator = maintenanceAdmin('moderateur');
        $this->actingAs($moderator)->get(route('admin.maintenance'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.dashboard'))->assertOk()->assertDontSee('href="'.route('admin.maintenance').'"', false);
    });

    it('n’est jamais bloqué par la maintenance : bandeau rouge et pastille « Actif »', function (): void {
        Settings::set('maintenance', true);

        $this->actingAs(maintenanceAdmin())->get(route('admin.maintenance'))
            ->assertOk()
            ->assertSee('Mode maintenance actif')
            ->assertSee('data-xm-maintenance-banner', false)
            ->assertSee('Maintenance en cours');
        $this->actingAs(maintenanceAdmin())->get(route('admin.dashboard'))->assertOk()->assertSee('Gérer la maintenance');
    });
});

describe('activation et désactivation', function (): void {
    it('exige un motif interne et la case de confirmation', function (): void {
        $this->actingAs(maintenanceAdmin());

        // Erreurs de validation : la modale reçoit l'évènement qui place le focus sur le premier champ invalide.
        Livewire::test(Index::class)
            ->set('reason', '')
            ->call('enable')
            ->assertHasErrors(['reason' => 'required', 'acknowledge' => 'accepted'])
            ->assertDispatched('xm-maint-invalid');

        expect(MaintenanceStatus::active())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'maintenance.enabled')->exists())->toBeFalse();
    });

    it('active la maintenance (durée estimée, message public) et la journalise avec le motif ; la personne garde sa session', function (): void {
        $admin = maintenanceAdmin();
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('reason', 'Migration de la base')
            ->set('duration', '60')
            ->set('message', 'Nous migrons la base de données.')
            ->set('acknowledge', true)
            ->call('enable')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.maintenance'));

        $snapshot = MaintenanceStatus::snapshot();
        $log = AuditLog::query()->where('action', 'maintenance.enabled')->sole();

        expect($snapshot->active)->toBeTrue()
            ->and($snapshot->message)->toBe('Nous migrons la base de données.')
            ->and($snapshot->endsAt?->equalTo(now()->addHour()))->toBeTrue()
            ->and($log->user_id)->toBe($admin->id)
            ->and(json_decode((string) $log->detail, true))->toMatchArray(['reason' => 'Migration de la base', 'message' => 'Nous migrons la base de données.'])
            ->and(MaintenanceWindow::query()->sole()->reason)->toBe('Migration de la base');

        // La session de l'admin reste ouverte et l'écran reste servi ; le public reçoit la page 503.
        $this->get(route('admin.maintenance'))->assertOk();
        $this->assertAuthenticatedAs($admin);
        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('Nous migrons la base de données.');
    });

    it('désactive en un clic et journalise', function (): void {
        $admin = maintenanceAdmin();
        app(EnableMaintenance::class)($admin, 'Mise à jour', now()->addHour(), 'Migration');
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->assertSee('Désactiver la maintenance')
            ->call('disable')
            ->assertRedirect(route('admin.maintenance'));

        expect(MaintenanceStatus::active())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'maintenance.disabled')->where('user_id', $admin->id)->exists())->toBeTrue()
            ->and(MaintenanceOverview::make()->state)->toBe(MaintenanceState::Ended);
    });

    it('n’active jamais deux fois : « depuis », fenêtre et historique de la première activation conservés', function (): void {
        $admin = maintenanceAdmin();
        $other = maintenanceAdmin('superadmin');
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
        app(EnableMaintenance::class)($admin, 'Première activation.', now()->addHour(), 'Migration');
        $start = Settings::string('maint_start');

        // Onglet resté ouvert ou second compte, vingt minutes plus tard.
        $this->travelTo(now()->addMinutes(20));
        $this->actingAs($other);
        Livewire::test(Index::class)
            ->set('reason', 'Seconde activation')
            ->set('acknowledge', true)
            ->call('enable')
            ->assertHasErrors(['enable'])
            ->assertDispatched('xm-maint-invalid')
            ->assertNoRedirect();

        expect(fn () => app(EnableMaintenance::class)($other, null, now()->addHour(), 'Encore'))->toThrow(MaintenanceAlreadyActive::class)
            ->and(Settings::string('maint_start'))->toBe($start)
            ->and(MaintenanceWindow::query()->count())->toBe(1)
            ->and(AuditLog::query()->where('action', 'maintenance.enabled')->count())->toBe(1)
            ->and(MaintenanceStatus::snapshot()->message)->toBe('Première activation.');

        // Même contrôle pour la commande de secours, et pendant une fenêtre planifiée en cours.
        $this->artisan('xomiton:maintenance', ['action' => 'on', '--minutes' => 30])
            ->expectsOutputToContain('La maintenance est déjà active')
            ->assertFailed();
        app(DisableMaintenance::class)($admin);
        MaintenanceWindow::factory()->ongoing()->create();
        expect(fn () => app(EnableMaintenance::class)($admin, null, null, 'Pendant la fenêtre'))->toThrow(MaintenanceAlreadyActive::class);
    });

    it('refuse d’activer ou de planifier depuis un compte qui perdrait l’accès (anti-verrouillage)', function (): void {
        $moderator = maintenanceAdmin('moderateur');

        expect(fn () => app(EnableMaintenance::class)($moderator, null, null, 'Essai'))->toThrow(MaintenanceLockout::class)
            ->and(fn () => app(ScheduleMaintenance::class)($moderator, now()->addDay(), now()->addDay()->addHour(), null, 0, 'Essai'))->toThrow(MaintenanceLockout::class)
            ->and(MaintenanceStatus::active())->toBeFalse();
    });
});

describe('planification', function (): void {
    it('planifie une fenêtre qui s’ouvre à l’heure puis se ferme seule, journalisée', function (): void {
        $admin = maintenanceAdmin();
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('startsAt', '2026-11-03T22:00')
            ->set('endsAt', '2026-11-03T23:30')
            ->set('announceHours', '24')
            ->set('windowMessage', 'Mise à jour de la messagerie.')
            ->set('windowReason', 'Laravel 12.4')
            ->call('schedule')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        // Saisie à l'heure du Bénin (UTC+1, comme la page publique), stockée en UTC.
        $window = MaintenanceWindow::query()->sole();
        Livewire::test(Index::class)->assertSee('Heure de Porto-Novo (UTC+01:00), comme sur la page publique.')
            ->assertSee("De 22\u{00A0}h à 23\u{00A0}h\u{00A0}30");
        expect($window->starts_at->toIso8601String())->toBe('2026-11-03T21:00:00+00:00')
            ->and($window->announce_hours)->toBe(24)
            ->and($window->reason)->toBe('Laravel 12.4')
            ->and(AuditLog::query()->where('action', 'maintenance.scheduled')->where('subject_id', $window->id)->exists())->toBeTrue();

        auth()->logout();
        $this->get('/')->assertOk();
        expect(MaintenanceOverview::make()->state)->toBe(MaintenanceState::Inactive);

        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(9, 0));
        expect(MaintenanceOverview::make()->state)->toBe(MaintenanceState::Announced);

        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(22, 0));
        $this->get('/')->assertStatus(503)->assertSee('Mise à jour de la messagerie.');
        $this->get('/sos')->assertOk();
        expect(MaintenanceOverview::make()->state)->toBe(MaintenanceState::Ongoing);

        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(23, 30));
        $this->get('/')->assertOk();
        expect(MaintenanceStatus::active())->toBeFalse()
            ->and(MaintenanceOverview::make()->state)->toBe(MaintenanceState::Ended);
    });

    it('refuse un chevauchement (422) et des dates impossibles', function (): void {
        $admin = maintenanceAdmin();
        $schedule = app(ScheduleMaintenance::class);
        $schedule($admin, now()->addDay()->setTime(22, 0), now()->addDay()->setTime(23, 0), null, 0, 'Première');

        try {
            $schedule($admin, now()->addDay()->setTime(22, 30), now()->addDay()->setTime(23, 30), null, 0, 'Seconde');
            $this->fail('Chevauchement accepté.');
        } catch (MaintenanceWindowOverlaps $exception) {
            expect($exception->status())->toBe(422)
                ->and($exception->errorCode())->toBe('maintenance_overlap')
                ->and($exception->errors())->toHaveKey('starts_at');
        }

        expect(fn () => $schedule($admin, now()->subHour(), now()->addHour(), null, 0, 'Passé'))->toThrow(MaintenanceWindowInvalid::class)
            ->and(fn () => $schedule($admin, now()->addDays(3), now()->addDays(3)->subHour(), null, 0, 'Ordre'))->toThrow(MaintenanceWindowInvalid::class)
            ->and(fn () => $schedule($admin, now()->addDays(3), now()->addDays(7), null, 0, 'Trop long'))->toThrow(MaintenanceWindowInvalid::class)
            ->and(MaintenanceWindow::query()->count())->toBe(1);

        // Contiguë : acceptée.
        $schedule($admin, now()->addDay()->setTime(23, 0), now()->addDay()->setTime(23, 45), null, 0, 'Suite');
        expect(MaintenanceWindow::query()->count())->toBe(2);

        $this->actingAs($admin);
        Livewire::test(Index::class)
            ->set('startsAt', MaintenancePage::local(now()->addDay()->setTime(22, 15))->format('Y-m-d\TH:i'))
            ->set('endsAt', MaintenancePage::local(now()->addDay()->setTime(22, 45))->format('Y-m-d\TH:i'))
            ->set('windowReason', 'Chevauchement')
            ->call('schedule')
            ->assertHasErrors(['startsAt']);
        Livewire::test(Index::class)
            ->set('startsAt', 'demain')
            ->set('windowReason', 'Format')
            ->call('schedule')
            ->assertHasErrors(['startsAt' => 'date_format']);
    });

    it('annule une fenêtre à venir (journalisée, jamais supprimée) puis refuse une seconde annulation', function (): void {
        $admin = maintenanceAdmin();
        $window = app(ScheduleMaintenance::class)($admin, now()->addDay(), now()->addDay()->addHour(), null, 24, 'Plan');
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->assertSee('Annuler la fenêtre')
            ->call('cancelWindow', $window->id)
            ->assertDispatched('toast');

        expect($window->fresh()?->is_active)->toBeFalse()
            ->and($window->fresh()?->cancelled_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'maintenance.window_cancelled')->exists())->toBeTrue()
            ->and(fn () => app(CancelMaintenanceWindow::class)($admin, $window))->toThrow(MaintenanceWindowClosed::class);
    });
});

describe('message public et liste blanche', function (): void {
    it('enregistre le message public (280 caractères au plus), appliqué aussi à la fenêtre en cours', function (): void {
        $admin = maintenanceAdmin();
        $window = MaintenanceWindow::factory()->ongoing()->create(['message' => 'Ancien message.']);
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->set('message', str_repeat('a', 281))
            ->call('saveMessage')
            ->assertHasErrors(['message' => 'max'])
            ->set('message', 'Nouveau message public.')
            ->call('saveMessage')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        expect(Settings::string('maint_msg'))->toBe('Nouveau message public.')
            ->and($window->fresh()?->message)->toBe('Nouveau message public.')
            ->and(AuditLog::query()->where('action', 'maintenance.message_updated')->exists())->toBeTrue();
    });

    it('affiche la liste blanche RG-01 en lecture seule : aucune propriété ni action ne la modifie', function (): void {
        $this->actingAs(maintenanceAdmin());

        $component = Livewire::test(Index::class)
            ->assertSee('/sos')
            ->assertSee('/api/v1/signalements')
            ->assertSee('/api/v1/urgences')
            ->assertSee('/api/v1/sync/push')
            ->assertSee('/api/v1/heartbeat')
            ->assertSee('/api/v1/violence-types')
            ->assertSee('/nouveau-mot-de-passe')
            ->assertSee('Verrouillé');

        expect(fn () => $component->set('whitelist', []))->toThrow(Exception::class)
            ->and(fn () => $component->call('whitelist'))->toThrow(Exception::class)
            ->and(EnsureNotInMaintenance::ALLOWED_PATHS)->toContain('sos', 'signaler', 'urgences', 'api/v1/sos', 'api/v1/signalements', 'api/v1/urgences', 'api/v1/sync/push', 'api/v1/heartbeat', 'api/v1/status', 'api/v1/zones', 'api/v1/violence-types', 'api/v1/needs');
    });

    it('montre l’aperçu en direct avec le message en cours de saisie, en clair ou en sombre', function (): void {
        $this->actingAs(maintenanceAdmin());

        Livewire::test(Index::class)
            ->set('message', 'Brouillon visible dans l’aperçu.')
            ->assertSeeHtml('data-xm-maintenance-preview')
            ->assertSee('Brouillon visible dans l’aperçu.')
            ->call('setPreviewTheme', 'dark')
            ->assertSeeHtml('data-xm-theme="dark"');

        expect(Settings::string('maint_msg'))->not->toBe('Brouillon visible dans l’aperçu.');
    });

    it('retrace l’historique : qui, quand, motif', function (): void {
        $admin = maintenanceAdmin();
        app(EnableMaintenance::class)($admin, null, null, 'Correctif de sécurité');
        app(DisableMaintenance::class)($admin);
        $this->actingAs($admin);

        Livewire::test(Index::class)
            ->assertSee('Maintenance activée')
            ->assertSee('Maintenance désactivée')
            ->assertSee("Motif\u{00A0}: Correctif de sécurité")
            ->assertSee($admin->pseudo);
    });
});

describe('commande de secours xomiton:maintenance', function (): void {
    it('active, décrit puis lève la maintenance, journalisée par « système »', function (): void {
        $this->artisan('xomiton:maintenance', ['action' => 'on', '--minutes' => 30, '--message' => 'Intervention urgente.', '--reason' => 'Incident base'])
            ->expectsOutput('Mode maintenance activé.')
            ->assertSuccessful();

        expect(MaintenanceStatus::active())->toBeTrue()
            ->and(MaintenanceStatus::snapshot()->message)->toBe('Intervention urgente.')
            ->and(AuditLog::query()->where('action', 'maintenance.enabled')->sole()->actor)->toBe(AuditLog::ACTOR_SYSTEM);

        $this->artisan('xomiton:maintenance', ['action' => 'status'])
            ->expectsOutput("Maintenance\u{00A0}: ACTIVE")
            ->expectsOutputToContain('Intervention urgente.')
            ->assertSuccessful();

        $this->artisan('xomiton:maintenance', ['action' => 'off', '--reason' => 'Fin de l’incident'])->assertSuccessful();
        expect(MaintenanceStatus::active())->toBeFalse();

        $this->artisan('xomiton:maintenance', ['action' => 'status'])->expectsOutput("Maintenance\u{00A0}: inactive")->assertSuccessful();
        $this->artisan('xomiton:maintenance', ['action' => 'peut-être'])->assertExitCode(2);
        $this->artisan('xomiton:maintenance', ['action' => 'on', '--minutes' => 'abc'])->assertExitCode(2);
        expect(MaintenanceStatus::active())->toBeFalse();
    });
});
