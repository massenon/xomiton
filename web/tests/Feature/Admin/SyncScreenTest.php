<?php

declare(strict_types=1);

use App\Application\Sync\Admin\ResolveSyncConflict;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Sync\Exceptions\ConflictResolutionRefused;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\ConflictStatus;
use App\Livewire\Admin\Sync\Index as Sync;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Population;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\ViolenceType;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\Support\CaseFixtures;

/*
 * P13-01 : écran /admin/synchronisation — `sync.manage` (admin, superadmin ; 403 modérateur, pair·e et membre), KPI,
 * appareils (filtres, révocation), résolution des conflits (serveur / appareil / fusion) appliquée au dossier avec les règles
 * de la collecte, choix journalisé sans les valeurs chiffrées (ADR-33), compteur de l'appareil, paramètres typés.
 */

const SYNC_SERVER_NOTES = 'Premier contact effectué.';
const SYNC_DEVICE_NOTES = 'Premier contact effectué ; rappel prévu jeudi.';

beforeEach(function (): void {
    CaseFixtures::seed();
});

function syncUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** @return array{0: CaseRecord, 1: SyncDevice, 2: SyncConflict} */
function syncConflict(string $field = 'notes', ?string $serverValue = SYNC_SERVER_NOTES, ?string $deviceValue = SYNC_DEVICE_NOTES): array
{
    $zone = CaseFixtures::zone();
    $peer = CaseFixtures::peer();
    $case = CaseRecord::factory()->create([
        'kind' => CaseKind::Vbg,
        'zone_id' => $zone->id,
        'commune_id' => CaseFixtures::commune($zone)->id,
        'population_id' => Population::query()->orderBy('position')->value('id'),
        'violence_type_id' => ViolenceType::query()->orderBy('position')->value('id'),
        'collected_by' => $peer->id,
        'notes' => SYNC_SERVER_NOTES,
    ]);
    $device = SyncDevice::factory()->create(['user_id' => $peer->id, 'label' => 'Tecno Spark', 'conflicts_count' => 1]);
    $conflict = SyncConflict::factory()->forDevice($device)->on($case, $field)->create(['server_value' => $serverValue, 'device_value' => $deviceValue]);

    return [$case, $device, $conflict];
}

it('sert l’écran à l’admin et au superadmin, le refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(syncUser($role))->get(route('admin.synchronisation'))
            ->assertOk()
            ->assertSeeLivewire(Sync::class)
            ->assertSee('Hors connexion & synchro')
            ->assertSee('Indicateurs clés')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="synchronisation"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(syncUser($role))->get(route('admin.synchronisation'))->assertForbidden();
    }
});

it('compte les appareils, les éléments en attente, les conflits ouverts et les appareils sans synchro récente', function (): void {
    $this->actingAs(syncUser('admin'));
    SyncDevice::factory()->create(['pending_count' => 7, 'last_sync_at' => now()->subHour()]);
    SyncDevice::factory()->create(['pending_count' => 0, 'last_sync_at' => now()->subDays(3)]);
    SyncDevice::factory()->create(['is_revoked' => true, 'pending_count' => 99, 'last_sync_at' => now()->subDays(9)]);
    [, , $conflict] = syncConflict();

    $component = Livewire::test(Sync::class);
    $stats = $component->instance()->stats();

    expect($stats['devices'])->toBe(3)
        ->and($stats['pending'])->toBe(7)
        ->and($stats['conflicts'])->toBe(1)
        ->and($stats['stale'])->toBeGreaterThanOrEqual(1)
        ->and($stats['revoked'])->toBe(1);
    $component->assertSee('Tecno Spark')->assertSee('Notes de suivi')->assertSee(SYNC_SERVER_NOTES);
});

it('filtre et trie les appareils, et ignore une valeur de filtre inconnue', function (): void {
    $this->actingAs(syncUser('admin'));
    SyncDevice::factory()->create(['label' => 'Appareil récent', 'last_sync_at' => now()->subMinutes(5), 'pending_count' => 0]);
    SyncDevice::factory()->create(['label' => 'Appareil ancien', 'last_sync_at' => now()->subDays(4), 'pending_count' => 3]);
    SyncDevice::factory()->create(['label' => 'Appareil perdu', 'is_revoked' => true]);

    Livewire::test(Sync::class)
        ->set('view', 'stale')->assertSee('Appareil ancien')->assertDontSee('Appareil récent')
        ->set('view', 'pending')->assertSee('Appareil ancien')->assertDontSee('Appareil récent')
        ->set('view', 'revoked')->assertSee('Appareil perdu')->assertDontSee('Appareil ancien')
        ->set('view', 'nimporte-quoi')->assertSee('Appareil récent')
        ->set('view', '')->set('search', 'ancien')->assertSee('Appareil ancien')->assertDontSee('Appareil récent');
});

it('garde la version du serveur : rien n’est écrit dans le dossier, le conflit est tranché et journalisé sans les valeurs', function (): void {
    $admin = syncUser('admin');
    $this->actingAs($admin);
    [$case, $device, $conflict] = syncConflict();

    Livewire::test(Sync::class)->call('resolve', $conflict->id, 'serveur')->assertDispatched('toast');

    $conflict->refresh();
    expect($conflict->status)->toBe(ConflictStatus::Resolved)
        ->and($conflict->resolution)->toBe('serveur')
        ->and($conflict->resolved_value)->toBe(SYNC_SERVER_NOTES)
        ->and($conflict->resolved_by)->toBe($admin->id)
        ->and($conflict->resolved_at)->not->toBeNull()
        ->and($case->fresh()->notes)->toBe(SYNC_SERVER_NOTES)
        ->and($device->fresh()->conflicts_count)->toBe(0);

    $log = AuditLog::query()->where('action', 'sync.conflict_resolved')->firstOrFail();
    expect($log->detail)->toContain('choix=serveur')->toContain('champ=notes')
        ->not->toContain('Premier contact')->not->toContain('rappel');
});

it('applique la version de l’appareil au dossier avec les règles de la collecte', function (): void {
    $this->actingAs(syncUser('admin'));
    [$case, $device, $conflict] = syncConflict();

    Livewire::test(Sync::class)->call('resolve', $conflict->id, 'appareil');

    expect($case->fresh()->notes)->toBe(SYNC_DEVICE_NOTES)
        ->and($conflict->fresh()->resolution)->toBe('appareil')
        ->and($conflict->fresh()->resolved_value)->toBe(SYNC_DEVICE_NOTES)
        ->and($device->fresh()->conflicts_count)->toBe(0)
        ->and(AuditLog::query()->where('action', 'cases.updated')->exists())->toBeTrue();
});

it('applique un statut de l’appareil seulement si la transition est permise', function (): void {
    $admin = syncUser('admin');
    [$case, , $conflict] = syncConflict('status', CaseStatus::Open->value, CaseStatus::Closed->value);
    $case->forceFill(['status' => CaseStatus::Open])->save();

    app(ResolveSyncConflict::class)($admin, $conflict, 'appareil');
    expect($case->fresh()->status)->toBe(CaseStatus::Closed);

    // Un dossier archivé ne bouge plus : le conflit reste ouvert et rien n'est écrit.
    [$archived, , $second] = syncConflict('notes');
    $archived->forceFill(['status' => CaseStatus::Archived, 'archived_at' => now()])->save();

    expect(fn () => app(ResolveSyncConflict::class)($admin, $second, 'appareil'))->toThrow(CaseLocked::class);
    expect($second->fresh()->status)->toBe(ConflictStatus::Open)
        ->and($archived->fresh()->notes)->toBe(SYNC_SERVER_NOTES);
});

it('fusionne à la main un champ texte, refuse une fusion sur un champ de référentiel et un conflit déjà résolu', function (): void {
    $this->actingAs(syncUser('admin'));
    [$case, , $conflict] = syncConflict();

    Livewire::test(Sync::class)
        ->call('openMerge', $conflict->id)
        ->assertSet('merged', SYNC_DEVICE_NOTES)
        ->set('merged', '')->call('saveMerge')->assertHasErrors(['merged'])
        ->set('merged', 'Premier contact effectué ; rappel prévu vendredi.')->call('saveMerge')->assertHasNoErrors();

    expect($case->fresh()->notes)->toBe('Premier contact effectué ; rappel prévu vendredi.')
        ->and($conflict->fresh()->resolution)->toBe('fusion')
        ->and($conflict->fresh()->resolved_value)->toBe('Premier contact effectué ; rappel prévu vendredi.');

    $admin = syncUser('admin');
    expect(fn () => app(ResolveSyncConflict::class)($admin, $conflict->fresh(), 'serveur'))->toThrow(ConflictResolutionRefused::class);

    [, , $reference] = syncConflict('commune_id', '1', '2');
    expect(fn () => app(ResolveSyncConflict::class)($admin, $reference, 'fusion', 'x'))->toThrow(ConflictResolutionRefused::class, 'ne se fusionne pas');
});

it('refuse une valeur d’appareil contraire aux règles du dossier sans rien écrire', function (): void {
    $admin = syncUser('admin');
    [$case, , $conflict] = syncConflict('violence_type_id', '1', '999999');

    expect(fn () => app(ResolveSyncConflict::class)($admin, $conflict, 'appareil'))->toThrow(ConflictResolutionRefused::class);
    expect($conflict->fresh()->status)->toBe(ConflictStatus::Open);
});

it('refuse la résolution à un compte sans `sync.manage`', function (): void {
    [, , $conflict] = syncConflict();

    expect(fn () => app(ResolveSyncConflict::class)(syncUser('moderateur'), $conflict, 'serveur'))->toThrow(AuthorizationException::class);

    $this->actingAs(syncUser('pair'));
    Livewire::test(Sync::class)->assertForbidden();
});

it('révoque un appareil, supprime ses abonnements push et journalise', function (): void {
    $admin = syncUser('admin');
    $this->actingAs($admin);
    $device = SyncDevice::factory()->create(['label' => 'Itel perdu']);
    PushSubscription::factory()->create(['sync_device_id' => $device->id]);

    Livewire::test(Sync::class)->call('revoke', $device->id)->assertDispatched('toast');

    expect($device->fresh()->is_revoked)->toBeTrue()
        ->and(PushSubscription::query()->where('sync_device_id', $device->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'sync.device_revoked')->where('detail', 'appareil=Itel perdu')->exists())->toBeTrue();
});

it('enregistre les paramètres typés de synchronisation et refuse les valeurs hors bornes', function (): void {
    $this->actingAs(syncUser('admin'));

    Livewire::test(Sync::class)
        ->set('interval', 30)->set('maxMb', 12)->set('wifiOnly', true)->set('offlineSync', false)
        ->call('saveSettings')->assertHasNoErrors()->assertDispatched('toast');

    expect(Setting::get('sync_interval_minutes'))->toBe(30)
        ->and(Setting::get('sync_max_mb'))->toBe(12)
        ->and(Setting::get('sync_wifi_only'))->toBeTrue()
        ->and(Setting::get('offline_sync'))->toBeFalse();

    Livewire::test(Sync::class)
        ->set('maxMb', 80)->call('saveSettings')->assertHasErrors(['maxMb'])
        ->set('maxMb', 5)->set('interval', 7)->call('saveSettings')->assertHasErrors(['interval']);

    expect(Setting::get('sync_max_mb'))->toBe(12);
});
