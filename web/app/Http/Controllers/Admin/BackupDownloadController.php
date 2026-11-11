<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement d'une sauvegarde (P13-03, `backups.download` : admin et superadmin) : l'archive ZIP chiffrée telle
 * qu'elle est stockée, jamais servie depuis un répertoire public ; la ligne d'audit `backup.downloaded` garde la trace.
 * Le mot de passe de l'archive est celui de BACKUP_ARCHIVE_PASSWORD (fichier .env), jamais affiché.
 */
final class BackupDownloadController extends Controller
{
    public function __invoke(Backup $backup): StreamedResponse
    {
        Gate::authorize('download', $backup);

        $disk = Storage::disk($backup->storage_disk);

        abort_unless($backup->isRestorable() && $backup->storage_path !== '' && $disk->exists($backup->storage_path), 404);

        Audit::log('backup.downloaded', $backup, 'nom='.$backup->name);

        return $disk->download($backup->storage_path, 'xomiton-sauvegarde-'.$backup->name.'.zip', [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
