<?php

declare(strict_types=1);

use App\Application\Platform\Backup\BackupService;
use App\Application\Platform\Backup\RestoreRunner;
use App\Application\Platform\Backup\RestoreState;
use App\Application\Platform\Backup\StartRestore;
use App\Domain\Platform\Exceptions\BackupException;
use App\Enums\BackupKind;
use App\Enums\CaseKind;
use App\Enums\JobStatus;
use App\Jobs\RestoreBackup;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\CaseRecord;
use App\Models\Population;
use App\Models\Setting;
use App\Models\User;
use App\Models\ViolenceType;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CaseFixtures;

/*
 * P13-03 : sauvegardes (ADR-22) — export par table en JSON par morceaux dans un ZIP chiffré avec manifeste, colonnes
 * chiffrées exportées déchiffrées, aperçu masqué, restauration en chaîne de jobs (sauvegarde de sécurité RG-13, maintenance
 * RG-14, vidage et import par morceaux, fin), saisie « RESTAURER », schéma trop récent refusé, reprise après échec,
 * « sauvegarde → key:generate → restauration → récit relisible ».
 */

const BACKUP_RECIT = 'Le récit confidentiel de la voisine qui rôde la nuit';

beforeEach(function (): void {
    CaseFixtures::seed();
    Storage::fake('local');
    Storage::fake('backups');
    config([
        'xomiton.backup.disk' => 'backups',
        'xomiton.backup.password' => 'mot-de-passe-de-test-1234',
        'xomiton.backup.chunk_rows' => 50,
    ]);
});

function backupUser(string $role = 'superadmin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function backupCase(string $notes = BACKUP_RECIT): CaseRecord
{
    $zone = CaseFixtures::zone();

    return CaseRecord::factory()->create([
        'kind' => CaseKind::Vbg,
        'zone_id' => $zone->id,
        'commune_id' => CaseFixtures::commune($zone)->id,
        'population_id' => Population::query()->orderBy('position')->value('id'),
        'violence_type_id' => ViolenceType::query()->orderBy('position')->value('id'),
        'notes' => $notes,
    ]);
}

function backupService(): BackupService
{
    return app(BackupService::class);
}

it('crée une archive ZIP chiffrée avec manifeste, tables par morceaux et récit déchiffré', function (): void {
    expect(BackupService::zipAvailable())->toBeTrue();
    $admin = backupUser('admin');
    backupCase();

    $backup = backupService()->create(BackupKind::Manual, $admin);

    expect($backup->status)->toBe(JobStatus::Done)
        ->and($backup->kind)->toBe(BackupKind::Manual)
        ->and($backup->checksum)->toHaveLength(64)
        ->and($backup->size_bytes)->toBeGreaterThan(0)
        ->and($backup->created_by)->toBe($admin->id)
        ->and(Storage::disk('backups')->exists($backup->storage_path))->toBeTrue()
        ->and(collect($backup->tables)->pluck('name')->all())->toContain('users', 'cases', 'settings')
        ->and(collect($backup->tables)->pluck('name')->all())->not->toContain('sessions', 'migrations', 'cache');

    $archive = backupService()->open($backup);
    $manifest = $archive->manifest();
    $rows = $archive->part('cases', 1);
    $archive->close();

    expect($manifest['format'])->toBe(1)
        ->and($manifest['schema_version'])->not->toBe('')
        ->and($manifest['key_fingerprint'])->toBe(backupService()->keyFingerprint())
        ->and($manifest['password_source'])->toBe('env')
        ->and($rows[0]['notes'])->toBe(BACKUP_RECIT);

    // En base la colonne reste chiffrée : l'export est la seule copie en clair, protégée par le mot de passe de l'archive.
    expect((string) DB::table('cases')->value('notes'))->not->toContain('voisine');

    // Le fichier est chiffré : sans le bon mot de passe, l'archive est illisible.
    config(['xomiton.backup.password' => 'autre-mot-de-passe']);
    expect(fn () => backupService()->open($backup))->toThrow(BackupException::class);
    expect(AuditLog::query()->where('action', 'backup.created')->exists())->toBeTrue();
});

it('découpe une grande table en plusieurs morceaux', function (): void {
    User::factory()->count(130)->create();

    $backup = backupService()->create(BackupKind::Manual);
    $archive = backupService()->open($backup);
    $users = collect($archive->manifest()['tables'])->firstWhere('name', 'users');

    expect($users['rows'])->toBe(130)
        ->and($users['parts'])->toBe(3)
        ->and(count($archive->part('users', 1)))->toBe(50)
        ->and(count($archive->part('users', 3)))->toBe(30);
    $archive->close();
});

it('montre un aperçu avec écart et un extrait dont les colonnes chiffrées et secrètes sont masquées', function (): void {
    backupCase();
    $backup = backupService()->create(BackupKind::Manual);
    backupCase('Autre récit');
    User::factory()->create();

    $preview = backupService()->preview($backup);
    $cases = collect($preview['tables'])->firstWhere('name', 'cases');
    $users = collect($preview['tables'])->firstWhere('name', 'users');

    expect($cases['rows'])->toBe(1)->and($cases['current'])->toBe(2)->and($cases['diff'])->toBe(1)
        ->and($users['diff'])->toBeGreaterThanOrEqual(1)
        ->and($preview['changed'])->toBeGreaterThanOrEqual(2);

    $sample = backupService()->sample($backup, 'cases');
    expect($sample)->toHaveCount(1)
        ->and($sample[0]['notes'])->toBe('••••')
        ->and(json_encode($sample))->not->toContain('voisine');

    $users = backupService()->sample($backup, 'users');
    expect($users[0]['password'])->toBe('••••')
        ->and(backupService()->sample($backup, 'table_inconnue'))->toBe([]);
});

it('exporte les secrets de paramètres déchiffrés et les rechiffre à la restauration', function (): void {
    Setting::query()->create(['key' => 'smtp_password', 'group' => 'smtp', 'type' => 'secret', 'value' => 'secret-smtp-1']);
    $backup = backupService()->create(BackupKind::Manual);

    $archive = backupService()->open($backup);
    $row = collect($archive->part('settings', 1))->firstWhere('key', 'smtp_password');
    $archive->close();

    expect($row['value'])->toBe('"secret-smtp-1"');
});

it('restaure une sauvegarde : sécurité RG-13, maintenance RG-14, données et récit relisibles, maintenance levée', function (): void {
    $super = backupUser('superadmin');
    $case = backupCase();
    $kept = User::factory()->create(['pseudo' => 'avant-sauvegarde']);
    Setting::query()->create(['key' => 'smtp_password', 'group' => 'smtp', 'type' => 'secret', 'value' => 'secret-smtp-1']);
    $backup = backupService()->create(BackupKind::Manual, $super);

    // Après la sauvegarde : un compte ajouté, un dossier supprimé, un paramètre modifié.
    $added = User::factory()->create(['pseudo' => 'apres-sauvegarde']);
    CaseRecord::query()->whereKey($case->id)->delete();
    Setting::query()->updateOrCreate(['key' => 'site_name'], ['group' => 'general', 'type' => 'string', 'value' => 'Modifié']);

    $state = app(StartRestore::class)($super, $backup, 'RESTAURER');

    // File « sync » des tests : la chaîne a déjà tourné jusqu'au bout au retour de l'appel.
    expect($state['status'])->toBe('done');

    $final = RestoreState::read();
    expect($final['status'])->toBe('done')
        ->and($final['step'])->toBe('finish')
        ->and(RestoreState::percent($final))->toBe(100)
        ->and($final['safety_backup_name'])->not->toBeNull();

    // RG-13 : une copie « avant restauration » existe, lisible et complète.
    $safety = Backup::query()->ofKind(BackupKind::BeforeRestore)->firstOrFail();
    expect($safety->status)->toBe(JobStatus::Done)
        ->and(collect($safety->tables)->firstWhere('name', 'users')['rows'])->toBe(User::query()->count() + 1);

    // Données revenues à l'état de la sauvegarde, récit relisible.
    expect(User::query()->where('pseudo', 'avant-sauvegarde')->exists())->toBeTrue()
        ->and(User::query()->where('pseudo', 'apres-sauvegarde')->exists())->toBeFalse()
        ->and(CaseRecord::query()->whereKey($case->id)->firstOrFail()->notes)->toBe(BACKUP_RECIT)
        ->and(User::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and($added->exists)->toBeTrue();

    // Le secret est rechiffré avec la clé courante et reste lisible.
    expect(Settings::get('smtp_password'))->toBe('secret-smtp-1')
        ->and((string) DB::table('settings')->where('key', 'smtp_password')->value('value'))->not->toContain('secret-smtp-1');

    // RG-14 : la maintenance a été levée à la fin ; la sauvegarde porte la date de restauration.
    expect(Settings::bool('maintenance'))->toBeFalse()
        ->and($backup->fresh()->restored_at)->not->toBeNull()
        ->and($backup->fresh()->restored_by)->toBe($super->id);

    // Le journal d'audit n'est jamais réécrit par une restauration et garde la trace.
    expect(AuditLog::query()->where('action', 'backup.restore_started')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'backup.restored')->exists())->toBeTrue();
});

it('rend le récit relisible après un changement d’APP_KEY entre la sauvegarde et la restauration', function (): void {
    $super = backupUser('superadmin');
    $case = backupCase();
    $backup = backupService()->create(BackupKind::Manual, $super);

    // Rotation de clé : `key:generate`, sans clé précédente.
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
    expect(fn () => CaseRecord::query()->whereKey($case->id)->firstOrFail()->notes)->toThrow(DecryptException::class);

    app(StartRestore::class)($super, $backup, 'RESTAURER');

    expect(RestoreState::read()['status'])->toBe('done')
        ->and(CaseRecord::query()->whereKey($case->id)->firstOrFail()->notes)->toBe(BACKUP_RECIT);
});

it('refuse la restauration sans la saisie exacte, sans la permission, pour un schéma trop récent ou une restauration en cours', function (): void {
    $super = backupUser('superadmin');
    $backup = backupService()->create(BackupKind::Manual, $super);
    $start = app(StartRestore::class);

    expect(fn () => $start($super, $backup, 'restaurer'))->toThrow(BackupException::class)
        ->and(fn () => $start(backupUser('admin'), $backup, 'RESTAURER'))->toThrow(AuthorizationException::class)
        ->and(RestoreState::read())->toBeNull();

    RestoreState::write(['id' => 'x', 'status' => 'running', 'step' => 'wipe']);
    expect(fn () => $start($super, $backup, 'RESTAURER'))->toThrow(BackupException::class);
    RestoreState::clear();

    // Schéma plus récent que celui de la base courante : refusé avant toute écriture.
    $archive = backupService()->open($backup);
    $archive->close();
    DB::table('migrations')->insert(['migration' => '2999_01_01_000000_future', 'batch' => 99]);
    $future = backupService()->create(BackupKind::Manual, $super);
    DB::table('migrations')->where('migration', '2999_01_01_000000_future')->delete();

    expect(fn () => $start($super, $future, 'RESTAURER'))->toThrow(BackupException::class, 'plus récente');
    expect(RestoreState::read())->toBeNull();
});

it('marque l’état en échec avec un message court et reprend là où la restauration s’est arrêtée', function (): void {
    $super = backupUser('superadmin');
    $backup = backupService()->create(BackupKind::Manual, $super);
    User::factory()->create(['pseudo' => 'apres-sauvegarde']);

    // Fichier d'archive disparu pendant la chaîne : échec propre, état conservé, reprise possible.
    $path = $backup->storage_path;
    $content = Storage::disk('backups')->get($path);
    Storage::disk('backups')->delete($path);
    RestoreState::write([
        'id' => 'abc', 'backup_id' => $backup->id, 'backup_name' => $backup->name, 'by' => $super->id, 'by_label' => $super->pseudo,
        'status' => 'queued', 'step' => 'safety', 'plan' => ['wipe' => [], 'import' => [], 'missing' => []], 'wipe_index' => 0,
        'import_index' => 0, 'part' => 1, 'rows_done' => 0, 'rows_total' => 0, 'safety_backup_id' => null, 'safety_backup_name' => null,
        'maintenance_enabled' => false, 'error' => null, 'started_at' => now()->toIso8601String(), 'finished_at' => null,
    ]);

    expect(app(RestoreRunner::class)->run('abc'))->toBeFalse();
    $state = RestoreState::read();

    expect($state['status'])->toBe('failed')
        ->and($state['error'])->toContain('BackupException')
        ->and(AuditLog::query()->where('action', 'backup.restore_failed')->exists())->toBeTrue()
        ->and(User::query()->where('pseudo', 'apres-sauvegarde')->exists())->toBeTrue();

    Storage::disk('backups')->put($path, $content);
    app(StartRestore::class)->resume($super);

    expect(RestoreState::read()['status'])->toBe('done')
        ->and(User::query()->where('pseudo', 'apres-sauvegarde')->exists())->toBeFalse();
});

it('supprime une sauvegarde (fichier et ligne) sauf la copie de sûreté, et applique la rotation des automatiques', function (): void {
    $super = backupUser('superadmin');
    $backup = backupService()->create(BackupKind::Manual, $super);
    $safety = backupService()->create(BackupKind::BeforeRestore, $super);

    backupService()->delete($backup, $super);

    expect(Backup::query()->whereKey($backup->id)->exists())->toBeFalse()
        ->and(Storage::disk('backups')->exists($backup->storage_path))->toBeFalse()
        ->and(fn () => backupService()->delete($safety, $super))->toThrow(BackupException::class)
        ->and(AuditLog::query()->where('action', 'backup.deleted')->exists())->toBeTrue();

    config(['xomiton.backup.keep_auto' => 2]);

    foreach (range(1, 4) as $i) {
        $auto = backupService()->create(BackupKind::Auto);
        $auto->forceFill(['created_at' => now()->subHours(10 - $i)])->save();
    }

    expect(backupService()->pruneAutomatic())->toBe(2)
        ->and(Backup::query()->ofKind(BackupKind::Auto)->count())->toBe(2);
});

it('inscrit une sauvegarde en échec quand l’écriture est impossible', function (): void {
    config(['xomiton.backup.disk' => 'disque-inexistant']);

    expect(fn () => backupService()->create(BackupKind::Manual))->toThrow(BackupException::class);

    $failed = Backup::query()->firstOrFail();
    expect($failed->status)->toBe(JobStatus::Failed)->and($failed->tables)->toBe([]);
});

it('crée une sauvegarde automatique par la commande nocturne (backups:run)', function (): void {
    $this->artisan('backups:run')->assertSuccessful();

    expect(Backup::query()->ofKind(BackupKind::Auto)->count())->toBe(1);
});

it('restaure en plusieurs jobs quand le budget est épuisé : chaque passage avance, aucun morceau n’est doublé', function (): void {
    Queue::fake();
    $super = backupUser('superadmin');
    $case = backupCase();
    User::factory()->count(60)->create();
    $backup = backupService()->create(BackupKind::Manual, $super);
    $users = User::query()->count();
    User::factory()->count(5)->create();
    CaseRecord::query()->whereKey($case->id)->delete();

    $state = app(StartRestore::class)($super, $backup, 'RESTAURER');
    Queue::assertPushed(RestoreBackup::class, 1);

    // 70 tables et plus (lots 1 à 17 du schéma) : un budget nul force un passage par étape.
    $hops = 1;

    while (app(RestoreRunner::class)->run($state['id'], 0.0)) {
        $hops++;
        $percent = RestoreState::percent(RestoreState::read());
        expect($percent)->toBeLessThan(100);
        expect($hops)->toBeLessThan(2000);
    }

    expect(count(Schema::getTables()))->toBeGreaterThanOrEqual(70)
        ->and($hops)->toBeGreaterThan(70)
        ->and(RestoreState::read()['status'])->toBe('done')
        ->and(User::query()->count())->toBe($users)
        ->and(CaseRecord::query()->whereKey($case->id)->firstOrFail()->notes)->toBe(BACKUP_RECIT);
});

it('termine une restauration complète en moins de 50 s par job', function (): void {
    $super = backupUser('superadmin');
    $backup = backupService()->create(BackupKind::Manual, $super);
    $started = microtime(true);

    app(StartRestore::class)($super, $backup, 'RESTAURER');

    expect(microtime(true) - $started)->toBeLessThan(50.0)
        ->and(RestoreState::read()['status'])->toBe('done');
});
