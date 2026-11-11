<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * État d'une restauration en chaîne de jobs (P13-03), gardé dans un **fichier** du disque local et non en base ni en
 * cache : la restauration réécrit la base et `optimize:clear` vide le cache, l'état doit leur survivre. Un seul état à
 * la fois (une restauration ne se double pas). Lu par l'écran Sauvegardes (suivi par polling).
 *
 * Champs : id, backup_id, backup_name, by, by_label, status (queued|running|done|failed), step (safety|maintenance|wipe|
 * import|finish), plan {wipe: list<string>, import: list<{name, parts}>, missing: list<string>}, wipe_index,
 * import_index, part (prochain morceau, à partir de 1), rows_done, rows_total, safety_backup_id, safety_backup_name,
 * maintenance_enabled, error, started_at, finished_at, updated_at.
 */
final class RestoreState
{
    public const FILE = 'backups/restore-state.json';

    public const STALE_AFTER_SECONDS = 7200;

    /**
     * @return array<string, mixed>|null
     */
    public static function read(): ?array
    {
        try {
            $disk = Storage::disk('local');

            if (! $disk->exists(self::FILE)) {
                return null;
            }

            $state = json_decode((string) $disk->get(self::FILE), true);

            return is_array($state) ? $state : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function write(array $state): void
    {
        $state['updated_at'] = now()->toIso8601String();
        Storage::disk('local')->put(self::FILE, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function clear(): void
    {
        Storage::disk('local')->delete(self::FILE);
    }

    /** Une restauration est-elle en cours (file d'attente ou exécution), récemment mise à jour ? */
    public static function active(?array $state = null): bool
    {
        $state ??= self::read();

        if ($state === null || ! in_array($state['status'] ?? null, ['queued', 'running'], true)) {
            return false;
        }

        $updated = strtotime((string) ($state['updated_at'] ?? '')) ?: 0;

        return time() - $updated < self::STALE_AFTER_SECONDS;
    }

    /**
     * Avancement en pourcentage : une unité par table à vider et par table à importer, plus la sauvegarde de sécurité,
     * la maintenance et la fin.
     *
     * @param  array<string, mixed>  $state
     */
    public static function percent(array $state): int
    {
        if (($state['status'] ?? null) === 'done') {
            return 100;
        }

        $plan = (array) ($state['plan'] ?? []);
        $wipe = count((array) ($plan['wipe'] ?? []));
        $import = count((array) ($plan['import'] ?? []));
        $total = 3 + $wipe + $import;

        $done = match ($state['step'] ?? 'safety') {
            'safety' => 0,
            'maintenance' => 1,
            'wipe' => 2 + (int) ($state['wipe_index'] ?? 0),
            'import' => 2 + $wipe + (int) ($state['import_index'] ?? 0),
            default => 2 + $wipe + $import,
        };

        return (int) min(99, round($done / max(1, $total) * 100));
    }
}
