<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use App\Application\Platform\DisableMaintenance;
use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenanceStatus;
use App\Domain\Platform\Exceptions\MaintenanceException;
use App\Enums\BackupKind;
use App\Models\Backup;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Exécution d'une restauration (P13-03, RG-13, RG-14), reprise d'un job à l'autre grâce à RestoreState :
 * 1. `safety` : sauvegarde `avant_restauration` de l'état actuel (RG-13) ;
 * 2. `maintenance` : mode maintenance (RG-14), sauf s'il est déjà actif ; calcul du plan (tables de la sauvegarde ∩ tables
 *    du schéma courant, moins `restore_skip_tables` : audit_logs, backups, maintenance_windows) ;
 * 3. `wipe` : vidage des tables, enfants avant parents ; 4. `import` : réécriture parents avant enfants, morceau par
 *    morceau (colonnes chiffrées rechiffrées avec la clé courante, `insertOrIgnore` : un morceau rejoué ne double rien) ;
 * 5. `finish` : caches vidés (`optimize:clear`), maintenance levée, `restored_at` posé sur la sauvegarde.
 * Le paramètre `maintenance` (et ses dates) n'est jamais écrasé. `run()` rend vrai quand il reste du travail.
 */
final class RestoreRunner
{
    public function __construct(
        private readonly BackupService $service,
        private readonly TableOrder $order,
        private readonly ColumnCipher $cipher,
    ) {}

    /**
     * @return bool vrai s'il reste des étapes (le job se ré-enfile)
     */
    public function run(string $id, ?float $budget = null): bool
    {
        $state = RestoreState::read();

        if ($state === null || ($state['id'] ?? null) !== $id || ! in_array($state['status'] ?? null, ['queued', 'running'], true)) {
            return false;
        }

        $deadline = microtime(true) + ($budget ?? (float) config('xomiton.backup.job_budget_seconds', 40));
        $state['status'] = 'running';
        RestoreState::write($state);

        $backup = Backup::query()->find((int) $state['backup_id']);
        $by = User::query()->find((int) $state['by']);

        if ($backup === null) {
            $this->fail($id, new \RuntimeException('Sauvegarde introuvable.'));

            return false;
        }

        $archive = null;

        try {
            Schema::disableForeignKeyConstraints();
            $archive = $this->service->open($backup);

            while (true) {
                switch ($state['step']) {
                    case 'safety':
                        $safety = $this->service->create(BackupKind::BeforeRestore, $by);
                        $state['safety_backup_id'] = $safety->getKey();
                        $state['safety_backup_name'] = $safety->name;
                        $state['step'] = 'maintenance';

                        break;
                    case 'maintenance':
                        $state['maintenance_enabled'] = $this->enableMaintenance($by, $backup);
                        $state['plan'] = $this->plan($archive);
                        $state['step'] = 'wipe';

                        break;
                    case 'wipe':
                        $wipe = (array) $state['plan']['wipe'];
                        $index = (int) $state['wipe_index'];

                        if ($index >= count($wipe)) {
                            $state['step'] = 'import';

                            break;
                        }

                        $this->wipe((string) $wipe[$index]);
                        $state['wipe_index'] = $index + 1;

                        break;
                    case 'import':
                        $import = (array) $state['plan']['import'];
                        $index = (int) $state['import_index'];

                        if ($index >= count($import)) {
                            $state['step'] = 'finish';

                            break;
                        }

                        $table = (string) $import[$index]['name'];
                        $parts = (int) $import[$index]['parts'];
                        $part = (int) $state['part'];

                        if ($part > $parts) {
                            $state['import_index'] = $index + 1;
                            $state['part'] = 1;

                            break;
                        }

                        $state['rows_done'] = (int) $state['rows_done'] + $this->importPart($archive, $table, $part);
                        $state['part'] = $part + 1;

                        break;
                    default:
                        $this->finish($state, $backup, $by);
                        RestoreState::write($state);

                        return false;
                }

                RestoreState::write($state);

                if (microtime(true) >= $deadline) {
                    return true;
                }
            }
        } catch (Throwable $exception) {
            $this->fail($id, $exception);

            return false;
        } finally {
            $archive?->close();
            Schema::enableForeignKeyConstraints();
        }
    }

    /** Échec : état « échec » avec un message court (jamais une valeur de donnée), journal, reprise possible. */
    public function fail(string $id, Throwable $exception): void
    {
        $state = RestoreState::read();

        if ($state === null || ($state['id'] ?? null) !== $id) {
            return;
        }

        Log::error('Restauration : échec.', ['restore' => $id, 'step' => $state['step'] ?? null, 'error' => $exception->getMessage()]);

        $state['status'] = 'failed';
        $state['error'] = mb_substr(class_basename($exception).' : '.preg_replace('/\s+/', ' ', $exception->getMessage()), 0, 300);
        RestoreState::write($state);

        $backup = Backup::query()->find((int) ($state['backup_id'] ?? 0));
        $by = User::query()->find((int) ($state['by'] ?? 0));

        try {
            Audit::log('backup.restore_failed', $backup, 'nom='.($state['backup_name'] ?? '').' ; étape='.($state['step'] ?? ''), $by);
        } catch (Throwable) {
            // Le journal ne doit pas masquer l'échec d'origine.
        }
    }

    /**
     * Active la maintenance pour la durée de la restauration ; faux si elle l'était déjà (alors on ne la lève pas à la fin).
     */
    private function enableMaintenance(?User $by, Backup $backup): bool
    {
        if (MaintenanceStatus::active()) {
            return false;
        }

        try {
            app(EnableMaintenance::class)(
                $by,
                (string) __('admin_system.backups.restore.maintenance_message'),
                now()->addMinutes(30),
                (string) __('admin_system.backups.restore.maintenance_reason', ['name' => $backup->name]),
            );
        } catch (MaintenanceException) {
            return false;
        }

        return true;
    }

    /**
     * @return array{wipe: list<string>, import: list<array{name: string, parts: int}>, missing: list<string>}
     */
    private function plan(BackupArchive $archive): array
    {
        $skip = (array) config('xomiton.backup.restore_skip_tables', []);
        $inBackup = [];

        foreach ((array) ($archive->manifest()['tables'] ?? []) as $entry) {
            $inBackup[(string) $entry['name']] = (int) ($entry['parts'] ?? 0);
        }

        $forward = $this->order->forward([...(array) config('xomiton.backup.exclude_tables', []), ...$skip]);
        $import = [];
        $wipe = [];

        foreach ($forward as $table) {
            if (isset($inBackup[$table])) {
                $import[] = ['name' => $table, 'parts' => $inBackup[$table]];
                $wipe[] = $table;
            }
        }

        $missing = array_values(array_diff(array_keys($inBackup), $forward, $skip));

        return ['wipe' => array_reverse($wipe), 'import' => $import, 'missing' => $missing];
    }

    private function wipe(string $table): void
    {
        $query = DB::table($table);

        if ($table === 'settings') {
            $query->whereNotIn('key', $this->keptSettings());
        }

        $query->delete();
    }

    /**
     * @return list<string>
     */
    private function keptSettings(): array
    {
        return array_values(array_map('strval', (array) config('xomiton.backup.restore_keep_settings', [])));
    }

    /** @return int lignes écrites */
    private function importPart(BackupArchive $archive, string $table, int $part): int
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $rows = $archive->part($table, $part);
        $kept = $table === 'settings' ? $this->keptSettings() : [];
        $prepared = [];

        foreach ($rows as $row) {
            if ($kept !== [] && in_array($row['key'] ?? null, $kept, true)) {
                continue;
            }

            $prepared[] = array_intersect_key($this->cipher->encryptRow($table, $row), $columns);
        }

        if ($prepared === []) {
            return 0;
        }

        $perInsert = max(1, intdiv(900, max(1, count($prepared[0]))));

        DB::transaction(function () use ($table, $prepared, $perInsert): void {
            foreach (array_chunk($prepared, $perInsert) as $chunk) {
                DB::table($table)->insertOrIgnore($chunk);
            }
        });

        return count($prepared);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function finish(array &$state, Backup $backup, ?User $by): void
    {
        Setting::flushCache();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('optimize:clear');

        // Le compte qui restaure peut ne pas exister à la date de la sauvegarde : jamais de clé étrangère orpheline.
        $actor = $by !== null && User::query()->whereKey($by->getKey())->exists() ? $by : null;

        if ($state['maintenance_enabled']) {
            app(DisableMaintenance::class)($actor, (string) __('admin_system.backups.restore.maintenance_reason', ['name' => $backup->name]));
        }

        $backup->forceFill(['restored_at' => now(), 'restored_by' => $actor?->getKey()])->save();
        Audit::log('backup.restored', $backup, sprintf('nom=%s ; sécurité=%s ; lignes=%d ; par=%s', $backup->name, (string) $state['safety_backup_name'], (int) $state['rows_done'], (string) $state['by_label']), $actor);

        $state['status'] = 'done';
        $state['step'] = 'finish';
        $state['finished_at'] = now()->toIso8601String();
    }
}
