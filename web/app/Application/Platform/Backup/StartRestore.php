<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use App\Domain\Platform\Exceptions\BackupException;
use App\Jobs\RestoreBackup;
use App\Models\Backup;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * Lance la restauration d'une sauvegarde (P13-03, RG-13) : superadmin (`backups.restore`), saisie « RESTAURER » revérifiée,
 * sauvegarde lisible et de schéma non postérieur, une seule restauration à la fois ; puis enfile la chaîne de jobs
 * RestoreBackup (sauvegarde `avant_restauration`, maintenance RG-14, vidage et import par morceaux, fin).
 */
final class StartRestore
{
    public function __construct(private readonly BackupService $service) {}

    /**
     * @return array<string, mixed> état initial de la restauration
     *
     * @throws AuthorizationException
     * @throws BackupException
     */
    public function __invoke(User $by, Backup $backup, string $word): array
    {
        if (! $by->can('restore', $backup)) {
            throw new AuthorizationException;
        }

        if (trim($word) !== (string) __('admin_system.backups.restore.word')) {
            throw BackupException::wordMismatch();
        }

        if (! $backup->isRestorable()) {
            throw BackupException::notRestorable();
        }

        if (RestoreState::active()) {
            throw BackupException::alreadyRunning();
        }

        $archive = $this->service->open($backup);

        try {
            $manifest = $archive->manifest();
        } finally {
            $archive->close();
        }

        if ((string) ($manifest['schema_version'] ?? '') > $this->service->schemaVersion()) {
            throw BackupException::schemaTooNew();
        }

        $state = [
            'id' => (string) Str::uuid(),
            'backup_id' => (int) $backup->getKey(),
            'backup_name' => $backup->name,
            'by' => (int) $by->getKey(),
            'by_label' => (string) $by->pseudo,
            'status' => 'queued',
            'step' => 'safety',
            'plan' => ['wipe' => [], 'import' => [], 'missing' => []],
            'wipe_index' => 0,
            'import_index' => 0,
            'part' => 1,
            'rows_done' => 0,
            'rows_total' => (int) $backup->rowsCount(),
            'safety_backup_id' => null,
            'safety_backup_name' => null,
            'maintenance_enabled' => false,
            'error' => null,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
        ];

        RestoreState::write($state);
        Audit::log('backup.restore_started', $backup, 'nom='.$backup->name, $by);
        RestoreBackup::dispatch($state['id']);

        return RestoreState::read() ?? $state;
    }

    /**
     * Reprend une restauration interrompue au point où elle s'est arrêtée.
     *
     * @throws AuthorizationException
     */
    public function resume(User $by): void
    {
        $state = RestoreState::read();

        if ($state === null || ($state['status'] ?? null) !== 'failed') {
            return;
        }

        $backup = Backup::query()->find((int) ($state['backup_id'] ?? 0));

        if ($backup === null || ! $by->can('restore', $backup)) {
            throw new AuthorizationException;
        }

        $state['status'] = 'queued';
        $state['error'] = null;
        RestoreState::write($state);
        Audit::log('backup.restore_resumed', $backup, 'nom='.$backup->name, $by);
        RestoreBackup::dispatch((string) $state['id']);
    }
}
