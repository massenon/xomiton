<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Application\Platform\Backup\BackupService;
use App\Application\Platform\Backup\MysqlDump;
use App\Domain\Platform\Exceptions\BackupException;
use App\Enums\BackupKind;
use App\Models\Backup;
use Illuminate\Console\Command;

/**
 * Sauvegarde nocturne (P13-03, ADR-22, planifiée à 02:00) : `mysqldump` compressé quand il est disponible (sauvegarde
 * officielle, D7), archive JSON chiffrée de repli (et pour l'écran Sauvegardes), puis rotation des sauvegardes
 * automatiques (`xomiton.backup.keep_auto`) et des copies de sûreté anciennes.
 */
class RunBackupCommand extends Command
{
    protected $signature = 'backups:run';

    protected $description = 'Sauvegarde nocturne : mysqldump si disponible, archive JSON chiffrée sinon, vers le stockage configuré (BACKUP_DISK)';

    public function handle(BackupService $service, MysqlDump $dump): int
    {
        $failed = false;
        $dumped = false;
        $name = Backup::nameFor(BackupKind::Auto);

        if ($dump->available()) {
            $path = $dump->run($name);
            $dumped = $path !== null;
            $dumped ? $this->info('mysqldump déposé : '.$path) : $this->warn('mysqldump en échec : archive JSON de repli.');
        } else {
            $this->line('mysqldump indisponible (pilote, proc_open ou binaire) : archive JSON de repli.');
        }

        try {
            $backup = $service->create(BackupKind::Auto);
            $this->info(sprintf('Sauvegarde %s créée (%s, %d tables).', $backup->name, $backup->humanSize(), count($backup->tables)));
        } catch (BackupException $exception) {
            $this->error($exception->getMessage());
            // Sans ext-zip, un mysqldump réussi suffit : la sauvegarde officielle existe.
            $failed = ! $dumped;
        }

        $pruned = $service->pruneAutomatic();
        $pruned > 0 && $this->line($pruned.' ancienne(s) sauvegarde(s) automatique(s) supprimée(s).');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
