<?php

declare(strict_types=1);

use App\Enums\BackupKind;
use App\Enums\ConflictStatus;
use App\Enums\JobStatus;
use App\Models\AppVersion;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\MaintenanceWindow;
use App\Models\Setting;
use App\Models\SyncConflict;
use App\Models\SyncCursor;
use App\Models\SyncDevice;
use App\Models\SyncOutboxReceipt;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\AppVersionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Lot 11 « platform » : une création par factory et une règle métier par table
 * (DATABASE_DICTIONARY.md §10). Nécessite l'intégration des lots pour RefreshDatabase (sync_devices, users).
 */

describe('settings', function (): void {
    it('crée un paramètre à clé unique, le lit depuis le cache et invalide ce cache à l’écriture', function (): void {
        $setting = Setting::factory()->key('site_name')->public()->create(['value' => 'Xomiton']);

        expect(Setting::get('site_name'))->toBe('Xomiton')
            ->and(Settings::string('site_name'))->toBe('Xomiton')
            ->and(Settings::get('inconnue', 'défaut'))->toBe('défaut')
            ->and(Cache::has(Setting::CACHE_KEY))->toBeTrue();

        Settings::set('site_name', 'Xomiton Bénin');

        expect(Cache::has(Setting::CACHE_KEY))->toBeFalse()
            ->and(Settings::get('site_name'))->toBe('Xomiton Bénin')
            ->and($setting->fresh()->value)->toBe('Xomiton Bénin');

        expect(fn () => Setting::factory()->key('site_name')->create())->toThrow(QueryException::class);
    });

    it('type les valeurs, chiffre les secrets en base et les exclut des valeurs publiques', function (): void {
        Setting::factory()->key('maintenance')->bool(false)->public()->create();
        Setting::factory()->key('session_minutes')->int(120)->create();
        Setting::factory()->key('twofa')->json(['on' => true, 'method' => 'sms'])->create();
        $secret = Setting::factory()->key('smtp_password')->secret('p@ss-smtp')->create();
        $created = Settings::set('nouvelle_cle', ['a' => 1]);

        $raw = DB::table('settings')->where('id', $secret->id)->value('value');

        expect(Settings::bool('maintenance', true))->toBeFalse()
            ->and(Settings::int('session_minutes'))->toBe(120)
            ->and(Settings::array('twofa'))->toBe(['on' => true, 'method' => 'sms'])
            ->and($raw)->not->toContain('p@ss-smtp')
            ->and($secret->fresh()->value)->toBe('p@ss-smtp')
            ->and(Settings::get('smtp_password'))->toBe('p@ss-smtp')
            ->and($created->type)->toBe(Setting::TYPE_JSON)
            ->and($created->group)->toBe(Setting::GROUP_GENERAL)
            ->and(Settings::public())->toHaveKey('maintenance')
            ->and(Settings::public())->not->toHaveKey('smtp_password')
            ->and(Setting::query()->public()->count())->toBe(1)
            ->and(Setting::query()->inGroup(Setting::GROUP_SMTP)->count())->toBe(1);
    });

    it('seede les clés du prototype de façon idempotente sans écraser les valeurs modifiées', function (): void {
        $this->seed(SettingsSeeder::class);
        $count = Setting::query()->count();

        Settings::set('site_name', 'Modifié');
        Settings::set('smtp_password', 'secret-admin');
        $this->seed(SettingsSeeder::class);

        expect(Setting::query()->count())->toBe($count)
            ->and($count)->toBeGreaterThanOrEqual(38)
            ->and(Settings::string('site_name'))->toBe('Modifié')
            ->and(Settings::string('timezone'))->toBe('Africa/Porto-Novo')
            ->and(Settings::string('platform_version'))->toBe('1.4.2')
            ->and(Settings::array('languages'))->toBe(['fr'])
            ->and(Settings::int('smtp_port'))->toBe(587)
            ->and(Settings::get('smtp_password'))->toBe('secret-admin')
            ->and(Setting::query()->forKey('smtp_password')->value('type'))->toBe(Setting::TYPE_SECRET)
            ->and(Settings::array('sync_settings')['interval'])->toBe(15)
            ->and(Settings::string('assistant_prompt'))->toContain('ESCALADE')
            ->and(Setting::query()->forKey('registrations_open')->value('group'))->toBe(Setting::GROUP_REGISTRATIONS);
    });
});

describe('app_versions', function (): void {
    it('crée une version unique, distingue déployée et planifiée et retrouve la version courante', function (): void {
        $old = AppVersion::factory()->version('1.4.0')->create(['deployed_at' => now()->subMonths(2)]);
        $current = AppVersion::factory()->version('1.4.2')->ofKind(AppVersion::KIND_FIX)->create(['deployed_at' => now()->subDay()]);
        $planned = AppVersion::factory()->version('1.5.0')->planned()->create();

        expect(AppVersion::current()?->id)->toBe($current->id)
            ->and($planned->isPlanned())->toBeTrue()
            ->and($planned->isNewerThan($current->version))->toBeTrue()
            ->and($old->isNewerThan($current->version))->toBeFalse()
            ->and(AppVersion::query()->deployed()->count())->toBe(2)
            ->and(AppVersion::query()->planned()->count())->toBe(1)
            ->and($current->kindBadge())->toBe('success')
            ->and(AppVersion::kindFromPrototype('Fonctionnalité'))->toBe(AppVersion::KIND_FEATURE)
            ->and(AppVersion::statusFromPrototype('déployée'))->toBe(AppVersion::STATUS_DEPLOYED);

        $planned->markDeployed();

        expect(AppVersion::current()?->id)->toBe($planned->id);

        expect(fn () => AppVersion::factory()->version('1.4.2')->create())->toThrow(QueryException::class);
    });

    it('seede les 3 versions du prototype sans doublon et sans replanifier une version déployée', function (): void {
        $this->seed(AppVersionsSeeder::class);
        AppVersion::query()->where('version', '1.5.0')->firstOrFail()->markDeployed();
        $this->seed(AppVersionsSeeder::class);

        expect(AppVersion::query()->count())->toBe(3)
            ->and(AppVersion::query()->where('version', '1.4.2')->value('kind'))->toBe(AppVersion::KIND_FIX)
            ->and(AppVersion::query()->where('version', '1.4.0')->value('kind'))->toBe(AppVersion::KIND_FEATURE)
            ->and(AppVersion::query()->where('version', '1.5.0')->firstOrFail()->isDeployed())->toBeTrue()
            ->and(AppVersion::current()?->version)->toBe('1.5.0');
    });
});

describe('maintenance_windows', function (): void {
    it('crée une fenêtre et détecte la maintenance en cours seulement pour une fenêtre active', function (): void {
        $ongoing = MaintenanceWindow::factory()->ongoing()->create();
        MaintenanceWindow::factory()->ongoing()->inactive()->create();
        MaintenanceWindow::factory()->past()->create();
        $upcoming = MaintenanceWindow::factory()->withoutMessage()->create();

        expect($ongoing->isOngoing())->toBeTrue()
            ->and(MaintenanceWindow::isUnderMaintenance())->toBeTrue()
            ->and(MaintenanceWindow::query()->ongoing()->count())->toBe(1)
            ->and(MaintenanceWindow::query()->upcoming()->first()?->id)->toBe($upcoming->id)
            ->and($upcoming->displayMessage())->toBe(MaintenanceWindow::DEFAULT_MESSAGE)
            ->and($ongoing->creator)->toBeInstanceOf(User::class);

        $ongoing->update(['is_active' => false]);

        expect(MaintenanceWindow::isUnderMaintenance())->toBeFalse();
    });
});

describe('backups', function (): void {
    it('crée une sauvegarde nommée à la seconde, caste son inventaire et refuse un nom en double', function (): void {
        $backup = Backup::factory()->create(['tables' => [['name' => 'users', 'rows' => 12], ['name' => 'posts', 'rows' => 30]], 'size_bytes' => 2_621_440]);
        $safety = Backup::factory()->ofKind(BackupKind::BeforeRestore)->create();
        $failed = Backup::factory()->status(JobStatus::Failed)->create();

        expect($backup->fresh()->kind)->toBe(BackupKind::Manual)
            ->and($failed->fresh()->tables)->toBe([])
            ->and($failed->rowsCount())->toBe(0)
            ->and($backup->status)->toBe(JobStatus::Done)
            ->and($backup->rowsCount())->toBe(42)
            ->and($backup->humanSize())->toBe('2,5 Mo')
            ->and($backup->isRestorable())->toBeTrue()
            ->and($safety->name)->toEndWith('-avant')
            ->and($safety->kind->isDeletable())->toBeFalse()
            ->and(BackupKind::fromPrototypeName($safety->name))->toBe(BackupKind::BeforeRestore)
            ->and(Backup::query()->completed()->count())->toBe(2)
            ->and(Backup::query()->ofKind(BackupKind::BeforeRestore)->count())->toBe(1)
            ->and(Backup::query()->restored()->count())->toBe(0);

        expect(fn () => Backup::factory()->create(['name' => $backup->name]))->toThrow(QueryException::class);
    });
});

describe('audit_logs', function (): void {
    it('journalise une action immuable, sans updated_at, et cible les entrées à purger', function (): void {
        $user = User::factory()->create();
        $log = AuditLog::factory()->ofAction('Paramètres')->on($user)->create(['user_id' => $user->id, 'actor' => $user->email]);
        // Actions fixées : l'action tirée au hasard par la fabrique pouvait valoir « Paramètres » (test instable).
        $anonymous = AuditLog::factory()->anonymous()->ofAction('Connexion')->create();
        AuditLog::factory()->expired()->ofAction('Connexion')->create();

        expect($log->updated_at)->toBeNull()
            ->and($log->subject)->toBeInstanceOf(User::class)
            ->and($anonymous->actorLabel())->toBe(AuditLog::ACTOR_ANONYMOUS)
            ->and(AuditLog::query()->forSubject($user)->count())->toBe(1)
            ->and(AuditLog::query()->ofAction('Paramètres')->count())->toBe(1)
            ->and(AuditLog::query()->olderThan()->count())->toBe(1)
            ->and(AuditLog::query()->between(now()->subDays(70), now())->count())->toBe(2);

        expect(fn () => $log->update(['detail' => 'falsifié']))->toThrow(LogicException::class);

        $user->forceDelete();

        expect($log->fresh()->user_id)->toBeNull()
            ->and($log->fresh()->actor)->toBe($user->email);
    });
});

describe('sync_outbox_receipts', function (): void {
    it('refuse deux reçus pour la même clé d’idempotence sur un appareil et compare les contenus rejoués', function (): void {
        $device = SyncDevice::factory()->create();
        $payload = ['code' => 'VBG-0001', 'kind' => 'VBG'];
        $receipt = SyncOutboxReceipt::factory()->forDevice($device)->withKey('11111111-1111-1111-1111-111111111111')->forPayload($payload)->create();

        expect($receipt->updated_at)->toBeNull()
            ->and($receipt->wasCreated())->toBeTrue()
            ->and($receipt->matchesPayload(['kind' => 'VBG', 'code' => 'VBG-0001']))->toBeTrue()
            ->and($receipt->matchesPayload(['code' => 'VBG-0002']))->toBeFalse()
            ->and(SyncOutboxReceipt::findForReplay($device->id, '11111111-1111-1111-1111-111111111111')?->id)->toBe($receipt->id)
            ->and(SyncOutboxReceipt::query()->accepted()->count())->toBe(1);

        expect(fn () => SyncOutboxReceipt::factory()->forDevice($device)->withKey('11111111-1111-1111-1111-111111111111')->create())
            ->toThrow(QueryException::class);

        SyncOutboxReceipt::factory()->withKey('11111111-1111-1111-1111-111111111111')->create();

        expect(SyncOutboxReceipt::query()->count())->toBe(2);

        $device->delete();

        expect(SyncOutboxReceipt::query()->count())->toBe(1);
    });
});

describe('sync_conflicts', function (): void {
    it('crée un conflit à résoudre, le tranche en conservant la bonne version et survit à la suppression de l’appareil', function (): void {
        $admin = User::factory()->create();
        $conflict = SyncConflict::factory()->create(['server_value' => 'ouvert', 'device_value' => 'clos']);
        SyncConflict::factory()->resolved()->create();

        expect($conflict->status)->toBe(ConflictStatus::Open)
            ->and($conflict->isOpen())->toBeTrue()
            ->and(SyncConflict::query()->open()->count())->toBe(1)
            ->and(SyncConflict::query()->resolved()->count())->toBe(1)
            ->and(ConflictStatus::fromPrototype('à résoudre'))->toBe(ConflictStatus::Open);

        $conflict->resolve(SyncConflict::RESOLUTION_DEVICE, $admin);

        expect($conflict->fresh()->status)->toBe(ConflictStatus::Resolved)
            ->and($conflict->resolved_value)->toBe('clos')
            ->and($conflict->resolver?->id)->toBe($admin->id)
            ->and($conflict->resolutionLabel())->toBe('version appareil conservée');

        expect(fn () => $conflict->resolve(SyncConflict::RESOLUTION_MERGE))->toThrow(InvalidArgumentException::class);

        $conflict->device->delete();

        expect($conflict->fresh()->device_id)->toBeNull();
    });
});

describe('sync_cursors', function (): void {
    it('tient un seul curseur par appareil et collection, qui n’avance jamais en arrière', function (): void {
        $device = SyncDevice::factory()->create();
        $cursor = SyncCursor::obtain($device, 'cases');

        expect($cursor->isFresh())->toBeTrue()
            ->and(SyncCursor::obtain($device, 'cases')->id)->toBe($cursor->id)
            ->and(SyncCursor::query()->count())->toBe(1);

        $cursor->advanceTo(42);
        $cursor->advanceTo(10);

        expect($cursor->fresh()->cursor)->toBe(42)
            ->and($cursor->pulled_at)->not->toBeNull()
            ->and(SyncCursor::query()->forDevice($device->id)->forCollection('cases')->exists())->toBeTrue();

        expect(fn () => SyncCursor::factory()->forDevice($device)->collection('cases')->create())->toThrow(QueryException::class);

        $device->delete();

        expect(SyncCursor::query()->count())->toBe(0);
    });
});
