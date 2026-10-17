<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Models\AuditLog;
use App\Models\GeneratedReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Purge des rapports générés expirés (P1-16 `reports:purge`, DATABASE_DICTIONARY.md §7 `generated_reports`) :
 * au-delà d'`expires_at`, le fichier est supprimé du disque `xomiton.reports.disk`, puis la ligne est
 * - marquée (fichier et taille effacés, `expires_at` conservé) quand une entrée du journal d'audit la référence
 *   (historique de génération, téléchargement ou envoi à conserver) ;
 * - supprimée quand rien ne la référence.
 * Idempotent : une ligne marquée ne correspond plus au critère `expired()` (fichier déjà absent).
 */
final class PurgeExpiredReports
{
    private const CHUNK_SIZE = 100;

    public const MARKED = 'marked';

    public const DELETED = 'deleted';

    public function disk(): string
    {
        return (string) config('xomiton.reports.disk', 'local');
    }

    public function run(): ReportsPurgeResult
    {
        $files = 0;
        $marked = 0;
        $deleted = 0;

        GeneratedReport::query()->expired()->chunkById(self::CHUNK_SIZE, function (Collection $reports) use (&$files, &$marked, &$deleted): void {
            foreach ($reports as $report) {
                if ($this->deleteFile($report)) {
                    $files++;
                }

                $this->purge($report) === self::MARKED ? $marked++ : $deleted++;
            }
        });

        Log::info('Rétention : rapports expirés purgés.', [
            'files_deleted' => $files,
            'rows_marked' => $marked,
            'rows_deleted' => $deleted,
            'disk' => $this->disk(),
        ]);

        return new ReportsPurgeResult($files, $marked, $deleted, $this->disk());
    }

    /**
     * Supprime le fichier du disque s'il existe encore.
     */
    public function deleteFile(GeneratedReport $report): bool
    {
        $path = trim((string) $report->file_path);

        if ($path === '') {
            return false;
        }

        $storage = Storage::disk($this->disk());

        if (! $storage->exists($path)) {
            return false;
        }

        return $storage->delete($path);
    }

    /**
     * Marque la ligne (fichier effacé) ou la supprime si rien ne la référence.
     *
     * @return self::MARKED|self::DELETED
     */
    public function purge(GeneratedReport $report): string
    {
        if ($this->isReferenced($report)) {
            $report->forceFill(['file_path' => null, 'file_size' => null])->saveQuietly();

            return self::MARKED;
        }

        $report->delete();

        return self::DELETED;
    }

    /**
     * Une ligne est référencée quand le journal d'audit la cite (génération, téléchargement, envoi).
     */
    public function isReferenced(GeneratedReport $report): bool
    {
        return AuditLog::query()->forSubject($report)->exists();
    }
}
