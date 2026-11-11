<?php

declare(strict_types=1);

use App\Application\Offline\ServiceWorkerScript;
use App\Application\Platform\Backup\BackupService;
use App\Application\Platform\Updates\RunOptimization;
use App\Enums\BackupKind;
use App\Livewire\Admin\Updates\Index as Updates;
use App\Models\AppVersion;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * P13-02 : écran /admin/mises-a-jour — lecture `updates.view` (admin, superadmin ; 403 modérateur, pair·e et membre),
 * écriture `updates.manage` (superadmin seul) : publier (version supérieure à l'actuelle), planifier (date du jour ou plus
 * tard), déployer, annuler, version minimale mobile, optimisation dont « Mise à jour du cache hors connexion ».
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('backups');
    config(['xomiton.backup.disk' => 'backups', 'xomiton.backup.password' => 'mot-de-passe-de-test-1234']);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
    AppVersion::factory()->version('1.4.2')->create(['deployed_at' => now()->subDays(10)]);
});

function updatesUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('sert l’écran à l’admin (lecture) et au superadmin, le refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(updatesUser($role))->get(route('admin.mises-a-jour'))
            ->assertOk()
            ->assertSeeLivewire(Updates::class)
            ->assertSee('v1.4.2')
            ->assertSee('Historique des versions')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="mises-a-jour"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(updatesUser($role))->get(route('admin.mises-a-jour'))->assertForbidden();
    }
});

it('masque les actions d’écriture à l’admin et refuse ses appels directs (403)', function (): void {
    $this->actingAs(updatesUser('admin'));
    $planned = AppVersion::factory()->version('1.5.0')->planned()->create();

    Livewire::test(Updates::class)
        ->assertSee('seul le superadmin peut publier')
        ->assertDontSee('Déployer')
        ->set('version', '1.4.3')->set('notes', 'Correctif de la synchronisation.')
        ->call('release')->assertForbidden();
    Livewire::test(Updates::class)->call('deploy', $planned->id)->assertForbidden();
    Livewire::test(Updates::class)->call('run', 'cache')->assertForbidden();

    expect(AppVersion::query()->where('version', '1.4.3')->exists())->toBeFalse()
        ->and($planned->fresh()->status)->toBe(AppVersion::STATUS_PLANNED);
});

it('publie une version supérieure à l’actuelle et reflète le paramètre de version, journalisé', function (): void {
    $super = updatesUser('superadmin');
    $this->actingAs($super);

    Livewire::test(Updates::class)
        ->set('version', '1.4.3')->set('kind', 'securite')->set('notes', 'Correction de la durée de session.')
        ->call('release')->assertHasNoErrors()->assertDispatched('toast');

    $row = AppVersion::query()->where('version', '1.4.3')->firstOrFail();
    expect($row->status)->toBe(AppVersion::STATUS_DEPLOYED)->and($row->kind)->toBe('securite')->and($row->created_by)->toBe($super->id)
        ->and(Settings::string('platform_version'))->toBe('1.4.3')
        ->and(Settings::string('last_update'))->toBe('2026-11-02')
        ->and(AuditLog::query()->where('action', 'update.published')->exists())->toBeTrue();
});

it('refuse un format invalide, des notes trop courtes, une version existante ou non supérieure', function (): void {
    $this->actingAs(updatesUser('superadmin'));

    Livewire::test(Updates::class)
        ->set('version', '1.5')->set('notes', 'Court')->call('release')->assertHasErrors(['version', 'notes'])
        ->set('version', '1.4.2')->set('notes', 'Notes assez longues pour passer.')->call('release')->assertHasErrors(['version'])
        ->set('version', '1.4.1')->call('release')->assertHasErrors(['version']);

    expect(AppVersion::query()->count())->toBe(1);
});

it('planifie à une date du jour ou postérieure, refuse le passé, déploie puis annule', function (): void {
    $super = updatesUser('superadmin');
    $this->actingAs($super);

    Livewire::test(Updates::class)
        ->set('version', '1.5.0')->set('notes', 'Nouvelle messagerie de groupe.')->set('mode', 'plan')
        ->set('scheduledFor', '2026-11-01')->call('release')->assertHasErrors(['scheduledFor'])
        ->set('scheduledFor', '2026-11-09')->call('release')->assertHasNoErrors();

    $planned = AppVersion::query()->where('version', '1.5.0')->firstOrFail();
    expect($planned->status)->toBe(AppVersion::STATUS_PLANNED)->and($planned->scheduled_for?->toDateString())->toBe('2026-11-08');
    expect(Settings::string('platform_version', '1.4.2'))->not->toBe('1.5.0');

    Livewire::test(Updates::class)->call('deploy', $planned->id)->assertDispatched('toast');
    expect($planned->fresh()->status)->toBe(AppVersion::STATUS_DEPLOYED)->and(Settings::string('platform_version'))->toBe('1.5.0');

    $other = AppVersion::factory()->version('1.6.0')->planned()->create();
    Livewire::test(Updates::class)->call('cancel', $other->id);
    expect($other->fresh()->status)->toBe(AppVersion::STATUS_CANCELLED);

    // Une version déjà déployée ne se redéploie pas ; un numéro annulé redevient libre.
    Livewire::test(Updates::class)->call('deploy', $planned->id)->assertDispatched('toast');
    Livewire::test(Updates::class)->set('version', '1.6.0')->set('notes', 'Version replanifiée après annulation.')->set('mode', 'plan')->set('scheduledFor', '2026-12-01')
        ->call('release')->assertHasNoErrors();
    expect(AppVersion::query()->where('version', '1.6.0')->count())->toBe(1);
});

it('règle la version minimale mobile sans dépasser la version actuelle', function (): void {
    $this->actingAs(updatesUser('superadmin'));

    Livewire::test(Updates::class)
        ->set('minVersion', '1.9.0')->call('saveMinimum')->assertHasErrors(['minVersion'])
        ->set('minVersion', 'abc')->call('saveMinimum')->assertHasErrors(['minVersion'])
        ->set('minVersion', '1.3.0')->call('saveMinimum')->assertHasNoErrors();

    expect(Settings::string('app_min_version'))->toBe('1.3.0')
        ->and(AuditLog::query()->where('action', 'update.minimum_set')->exists())->toBeTrue();
});

it('« Mise à jour du cache hors connexion » incrémente la version du service worker', function (): void {
    $super = updatesUser('superadmin');
    $this->actingAs($super);
    $before = app(ServiceWorkerScript::class)->version();

    Livewire::test(Updates::class)->call('run', 'sw')->assertDispatched('toast');

    expect(app(ServiceWorkerScript::class)->version())->not->toBe($before)
        ->and(Settings::int(ServiceWorkerScript::REVISION_SETTING))->toBe(1)
        ->and(RunOptimization::state()['sw']['by'])->toBe($super->pseudo)
        ->and(AuditLog::query()->where('action', 'optimization.sw')->exists())->toBeTrue();

    Livewire::test(Updates::class)->assertSee('Dernier lancement');
});

it('lance les autres tâches d’optimisation et refuse une tâche inconnue', function (): void {
    $this->actingAs(updatesUser('superadmin'));
    AuditLog::factory()->create(['created_at' => now()->subDays(400)]);

    $component = Livewire::test(Updates::class);

    foreach (['cache', 'sitemap', 'logs', 'backup'] as $task) {
        $component->call('run', $task)->assertDispatched('toast');
    }

    expect(array_keys(RunOptimization::state()))->toEqualCanonicalizing(['cache', 'sitemap', 'logs', 'backup'])
        ->and(AuditLog::query()->where('created_at', '<', now()->subDays(300))->count())->toBe(0)
        ->and(app(BackupService::class)->preview(App\Models\Backup::query()->firstOrFail())['rows'])->toBeGreaterThan(0)
        ->and(App\Models\Backup::query()->ofKind(BackupKind::Manual)->count())->toBe(1);

    $component->call('run', 'rm-rf')->assertDispatched('toast');
    expect(RunOptimization::state())->not->toHaveKey('rm-rf');
});

it('classe les contenus les plus consultés et masque une adoption inférieure à 5 membres', function (): void {
    $this->actingAs(updatesUser('superadmin'));
    App\Models\Thematique::factory()->create(['title' => 'Comprendre le consentement', 'views_count' => 120, 'status' => 'publie', 'published_at' => now()->subDay()]);
    App\Models\Resource::factory()->create(['title' => 'Guide des droits', 'views_count' => 40, 'status' => 'publie', 'published_at' => now()->subDay()]);
    User::factory()->count(2)->create(['last_seen_at' => now()->subDay()]);

    Livewire::test(Updates::class)
        ->assertSeeInOrder(['Comprendre le consentement', 'Guide des droits'])
        ->assertSee('données masquées');
});

it('exige le rôle superadmin pour lancer une tâche même par appel direct du cas d’usage', function (): void {
    expect(fn () => app(RunOptimization::class)(updatesUser('admin'), 'cache'))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect(Setting::query()->where('key', 'optim_state')->exists())->toBeFalse();
});
