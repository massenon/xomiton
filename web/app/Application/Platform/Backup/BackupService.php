<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use App\Domain\Platform\Exceptions\BackupException;
use App\Enums\BackupKind;
use App\Enums\JobStatus;
use App\Models\Backup;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Sauvegardes de la base (P13-03, ADR-22) : export table par table en JSON **par morceaux** (`chunk_rows` lignes, fichier
 * `tables/<table>/<n>.json`), colonnes chiffrées exportées déchiffrées (ColumnCipher), `manifest.json` (version du
 * schéma, empreinte d'APP_KEY, inventaire), le tout dans un ZIP chiffré AES-256 par mot de passe
 * (`xomiton.backup.password`, sinon dérivé d'APP_KEY), déposé sur le disque `xomiton.backup.disk` (Q9). La sauvegarde
 * officielle reste le mysqldump planifié (MysqlDump) ; ce ZIP alimente l'aperçu, le téléchargement et le repli de
 * restauration. Sans ext-zip : BackupException::zipMissing().
 */
final class BackupService
{
    public const MANIFEST = 'manifest.json';

    public function __construct(
        private readonly TableOrder $order,
        private readonly ColumnCipher $cipher,
    ) {}

    public static function zipAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /** Chemin d'un morceau dans l'archive. */
    public static function entry(string $table, int $part): string
    {
        return 'tables/'.$table.'/'.$part.'.json';
    }

    /** Mot de passe de l'archive : variable d'environnement, sinon dérivé d'APP_KEY (l'archive dépend alors de la clé). */
    public function password(): string
    {
        $configured = (string) config('xomiton.backup.password', '');

        return $configured !== '' ? $configured : self::derive((string) config('app.key'));
    }

    public function passwordSource(): string
    {
        return (string) config('xomiton.backup.password', '') !== '' ? 'env' : 'app_key';
    }

    /**
     * Mots de passe à essayer pour ouvrir une archive : le courant, puis ceux dérivés des clés précédentes.
     *
     * @return list<string>
     */
    public function passwordCandidates(): array
    {
        $candidates = [$this->password()];

        foreach ((array) config('app.previous_keys', []) as $previous) {
            if (is_string($previous) && $previous !== '') {
                $candidates[] = self::derive($previous);
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function derive(string $key): string
    {
        return hash_hmac('sha256', 'xomiton-backup', $key);
    }

    public function keyFingerprint(): string
    {
        return substr(hash_hmac('sha256', 'xomiton-key-fingerprint', (string) config('app.key')), 0, 16);
    }

    /** Dernière migration appliquée : version du schéma portée par le manifeste. */
    public function schemaVersion(): string
    {
        try {
            return (string) (DB::table('migrations')->orderByDesc('id')->value('migration') ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Crée une sauvegarde complète (ZIP chiffré) et l'inscrit dans `backups`.
     *
     * @throws BackupException ext-zip absente ou échec (une ligne « échec » est alors inscrite)
     */
    public function create(BackupKind $kind, ?User $by = null): Backup
    {
        if (! self::zipAvailable()) {
            throw BackupException::zipMissing();
        }

        $name = $this->uniqueName($kind);
        $work = storage_path('framework/backup-'.Str::random(12));

        try {
            if (! @mkdir($work, 0700, true) && ! is_dir($work)) {
                throw new \RuntimeException('Dossier de travail impossible à créer.');
            }

            $inventory = $this->export($work);
            $manifest = [
                'format' => 1,
                'name' => $name,
                'kind' => $kind->value,
                'created_at' => now()->toIso8601String(),
                'app' => (string) config('app.name'),
                'schema_version' => $this->schemaVersion(),
                'key_fingerprint' => $this->keyFingerprint(),
                'password_source' => $this->passwordSource(),
                'chunk_rows' => $this->chunkRows(),
                'undecryptable_values' => $inventory['undecryptable'],
                'tables' => $inventory['tables'],
            ];

            $zipPath = $work.'/archive.zip';
            $this->zip($zipPath, $manifest, $work, $inventory['files']);

            $directory = trim((string) config('xomiton.backup.directory', 'backups'), '/');
            $path = $directory.'/'.$name.'.zip';
            $disk = (string) config('xomiton.backup.disk', Backup::DEFAULT_DISK);
            $stream = fopen($zipPath, 'rb');

            if ($stream === false || ! Storage::disk($disk)->writeStream($path, $stream)) {
                throw new \RuntimeException('Écriture de l’archive impossible sur le disque « '.$disk.' ».');
            }

            fclose($stream);

            $backup = Backup::query()->create([
                'name' => $name,
                'kind' => $kind,
                'size_bytes' => (int) filesize($zipPath),
                'tables' => array_map(static fn (array $table): array => ['name' => $table['name'], 'rows' => $table['rows']], $inventory['tables']),
                'storage_disk' => $disk,
                'storage_path' => $path,
                'checksum' => hash_file('sha256', $zipPath) ?: null,
                'status' => JobStatus::Done,
                'created_by' => $by?->getKey(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Sauvegarde : échec.', ['name' => $name, 'error' => $exception->getMessage()]);

            Backup::query()->create([
                'name' => $name,
                'kind' => $kind,
                'size_bytes' => 0,
                'tables' => [],
                'storage_disk' => (string) config('xomiton.backup.disk', Backup::DEFAULT_DISK),
                'storage_path' => '',
                'status' => JobStatus::Failed,
                'created_by' => $by?->getKey(),
            ]);
            $this->audit('backup.failed', null, 'nom='.$name, $by);

            throw BackupException::failed();
        } finally {
            $this->removeDirectory($work);
        }

        $this->audit('backup.created', $backup, sprintf('nom=%s ; type=%s ; tables=%d ; lignes=%d ; taille=%d', $name, $kind->value, count($backup->tables), $backup->rowsCount(), $backup->size_bytes), $by);

        return $backup;
    }

    /**
     * Ouvre l'archive d'une sauvegarde (essaie le mot de passe courant puis ceux des clés précédentes).
     *
     * @throws BackupException archive absente, corrompue ou mot de passe inconnu
     */
    public function open(Backup $backup): BackupArchive
    {
        if (! self::zipAvailable() || $backup->status !== JobStatus::Done || $backup->storage_path === '') {
            throw BackupException::unreadable();
        }

        $disk = Storage::disk($backup->storage_disk);

        if (! $disk->exists($backup->storage_path)) {
            throw BackupException::unreadable();
        }

        $temporary = null;
        $path = null;

        if ($this->isLocal($backup->storage_disk)) {
            $path = $disk->path($backup->storage_path);
        } else {
            $temporary = tempnam(sys_get_temp_dir(), 'xmbk');
            $source = $disk->readStream($backup->storage_path);
            $target = $temporary === false ? false : fopen($temporary, 'wb');

            if (! is_resource($source) || $target === false) {
                throw BackupException::unreadable();
            }

            stream_copy_to_stream($source, $target);
            fclose($target);
            $path = $temporary;
        }

        foreach ($this->passwordCandidates() as $candidate) {
            $zip = new ZipArchive;

            if ($zip->open((string) $path) !== true) {
                continue;
            }

            $zip->setPassword($candidate);
            $json = $zip->getFromName(self::MANIFEST);
            $manifest = $json === false ? null : json_decode($json, true);

            if (is_array($manifest)) {
                return new BackupArchive($zip, $manifest, $temporary);
            }

            $zip->close();
        }

        if ($temporary !== null && is_file($temporary)) {
            @unlink($temporary);
        }

        throw BackupException::unreadable();
    }

    private function isLocal(string $disk): bool
    {
        return (string) config('filesystems.disks.'.$disk.'.driver') === 'local';
    }

    /**
     * Aperçu : inventaire par collection avec écart par rapport aux données actuelles.
     *
     * @return array{name: string, created_at: string, schema_version: string, size: int, tables: list<array{name: string, rows: int, parts: int, current: int|null, diff: int|null}>, rows: int, changed: int}
     */
    public function preview(Backup $backup): array
    {
        $archive = $this->open($backup);

        try {
            $manifest = $archive->manifest();
        } finally {
            $archive->close();
        }

        $tables = [];
        $changed = 0;
        $total = 0;

        foreach ((array) ($manifest['tables'] ?? []) as $entry) {
            $name = (string) ($entry['name'] ?? '');
            $rows = (int) ($entry['rows'] ?? 0);
            $current = Schema::hasTable($name) ? DB::table($name)->count() : null;
            $diff = $current === null ? null : $current - $rows;
            $changed += $diff !== null && $diff !== 0 ? 1 : 0;
            $total += $rows;
            $tables[] = ['name' => $name, 'rows' => $rows, 'parts' => (int) ($entry['parts'] ?? 0), 'current' => $current, 'diff' => $diff];
        }

        return [
            'name' => (string) ($manifest['name'] ?? $backup->name),
            'created_at' => (string) ($manifest['created_at'] ?? ''),
            'schema_version' => (string) ($manifest['schema_version'] ?? ''),
            'size' => $backup->size_bytes,
            'tables' => $tables,
            'rows' => $total,
            'changed' => $changed,
        ];
    }

    /**
     * Extrait masqué : les premières lignes d'une collection (colonnes chiffrées et secrets remplacés par des points).
     *
     * @return list<array<string, mixed>>
     */
    public function sample(Backup $backup, string $table): array
    {
        $archive = $this->open($backup);

        try {
            $known = array_column((array) ($archive->manifest()['tables'] ?? []), 'parts', 'name');

            if (! isset($known[$table]) || (int) $known[$table] < 1) {
                return [];
            }

            $rows = array_slice($archive->part($table, 1), 0, max(1, (int) config('xomiton.backup.preview_rows', 5)));
        } finally {
            $archive->close();
        }

        $encrypted = $this->cipher->columnsOf($table);
        $pattern = (string) config('xomiton.backup.mask_columns', '/hash|pass|token|secret|pin|otp|key/i');

        return array_map(function (array $row) use ($encrypted, $pattern, $table): array {
            foreach ($row as $column => $value) {
                $sensitive = in_array($column, $encrypted, true) || preg_match($pattern, (string) $column) === 1
                    || ($table === 'settings' && $column === 'value' && ($row['type'] ?? null) === 'secret');

                if ($sensitive && $value !== null && $value !== '') {
                    $row[$column] = '••••';
                } elseif (is_string($value) && mb_strlen($value) > 120) {
                    $row[$column] = mb_substr($value, 0, 120).'…';
                }
            }

            return $row;
        }, $rows);
    }

    /**
     * Supprime l'archive et la ligne (superadmin ; la copie de sûreté d'une restauration n'est pas supprimable).
     */
    public function delete(Backup $backup, ?User $by = null): void
    {
        if (! $backup->kind->isDeletable()) {
            throw BackupException::notDeletable();
        }

        if ($backup->storage_path !== '') {
            Storage::disk($backup->storage_disk)->delete($backup->storage_path);
        }

        $name = $backup->name;
        $backup->delete();
        $this->audit('backup.deleted', null, 'nom='.$name, $by);
    }

    /** Conserve les `keep_auto` dernières sauvegardes automatiques et supprime les plus anciennes. */
    public function pruneAutomatic(): int
    {
        $keep = max(1, (int) config('xomiton.backup.keep_auto', 14));
        $old = Backup::query()->ofKind(BackupKind::Auto)->latestFirst()->get()->slice($keep);

        foreach ($old as $backup) {
            if ($backup->storage_path !== '') {
                Storage::disk($backup->storage_disk)->delete($backup->storage_path);
            }

            $backup->delete();
        }

        return $old->count();
    }

    public function audit(string $action, ?Backup $subject, string $detail, ?User $by): void
    {
        Audit::log($action, $subject, $detail, $by);
    }

    /**
     * @return array{tables: list<array{name: string, rows: int, parts: int, columns: list<string>}>, files: list<string>, undecryptable: int}
     */
    private function export(string $work): array
    {
        $tables = [];
        $files = [];
        $undecryptable = 0;
        $size = $this->chunkRows();

        foreach ($this->order->forward((array) config('xomiton.backup.exclude_tables', [])) as $table) {
            $rows = 0;
            $parts = 0;

            $this->chunks($table, $size, function (array $batch) use ($table, $work, &$rows, &$parts, &$files, &$undecryptable): void {
                $decoded = [];

                foreach ($batch as $row) {
                    [$plain, $failed] = $this->cipher->decryptRow($table, $row);
                    $decoded[] = $plain;
                    $undecryptable += $failed;
                }

                $parts++;
                $rows += count($decoded);
                $relative = self::entry($table, $parts);
                $absolute = $work.'/'.$relative;

                if (! is_dir(dirname($absolute))) {
                    mkdir(dirname($absolute), 0700, true);
                }

                file_put_contents($absolute, json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
                $files[] = $relative;
            });

            $tables[] = ['name' => $table, 'rows' => $rows, 'parts' => $parts, 'columns' => Schema::getColumnListing($table)];
        }

        return ['tables' => $tables, 'files' => $files, 'undecryptable' => $undecryptable];
    }

    /**
     * Lignes d'une table par paquets, dans un ordre stable (clé primaire simple : pagination par clé).
     *
     * @param  callable(list<array<string, mixed>>): void  $each
     */
    private function chunks(string $table, int $size, callable $each): void
    {
        $primary = $this->primaryColumns($table);

        if (count($primary) === 1) {
            $key = $primary[0];
            $last = null;

            while (true) {
                $query = DB::table($table)->orderBy($key)->limit($size);

                if ($last !== null) {
                    $query->where($key, '>', $last);
                }

                $batch = $query->get()->map(static fn (object $row): array => (array) $row)->all();

                if ($batch === []) {
                    return;
                }

                $each($batch);
                $last = $batch[array_key_last($batch)][$key];

                if (count($batch) < $size) {
                    return;
                }
            }
        }

        $order = $primary !== [] ? $primary : Schema::getColumnListing($table);
        $offset = 0;

        while (true) {
            $query = DB::table($table)->limit($size)->offset($offset);

            foreach ($order as $column) {
                $query->orderBy($column);
            }

            $batch = $query->get()->map(static fn (object $row): array => (array) $row)->all();

            if ($batch === []) {
                return;
            }

            $each($batch);
            $offset += $size;

            if (count($batch) < $size) {
                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function primaryColumns(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (! empty($index['primary'])) {
                return array_values(array_map('strval', (array) $index['columns']));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $files
     */
    private function zip(string $zipPath, array $manifest, string $work, array $files): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Archive impossible à créer.');
        }

        $zip->setPassword($this->password());
        $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $zip->setEncryptionName(self::MANIFEST, ZipArchive::EM_AES_256);

        foreach ($files as $relative) {
            $zip->addFile($work.'/'.$relative, $relative);
            $zip->setEncryptionName($relative, ZipArchive::EM_AES_256);
        }

        if (! $zip->close()) {
            throw new \RuntimeException('Archive impossible à finaliser.');
        }
    }

    private function chunkRows(): int
    {
        return max(50, (int) config('xomiton.backup.chunk_rows', 1000));
    }

    private function uniqueName(BackupKind $kind): string
    {
        $name = Backup::nameFor($kind);
        $candidate = $name;
        $i = 1;

        while (Backup::query()->where('name', $candidate)->exists()) {
            $candidate = $name.'-'.(++$i);
        }

        return $candidate;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
