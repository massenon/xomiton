<?php

declare(strict_types=1);

namespace App\Application\Maintenance;

use FilesystemIterator;
use Generator;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Purge des entrées expirées du cache `file` (P1-16 `cache:purge-expired`, D7 : `CACHE_STORE=file` sur cPanel).
 * Le pilote `file` de Laravel ne supprime une entrée expirée qu'à sa prochaine lecture : les clés jamais relues
 * s'accumulent dans storage/framework/cache/data. Chaque fichier commence par son horodatage d'expiration Unix
 * sur 10 chiffres (Illuminate\Cache\FileStore::put) : il suffit de lire cet en-tête, par lots, et de supprimer
 * les fichiers échus. Les fichiers sans en-tête reconnaissable (.gitignore, fichiers étrangers) sont ignorés.
 * Pour tout autre pilote (database, redis…), la commande ne fait rien.
 */
final class PurgeExpiredCache
{
    /** Nombre de fichiers lus par lot avant de passer au suivant. */
    public const BATCH_SIZE = 500;

    private const HEADER_LENGTH = 10;

    public function store(): string
    {
        return (string) config('cache.default', 'file');
    }

    public function driver(): string
    {
        return (string) config(sprintf('cache.stores.%s.driver', $this->store()), '');
    }

    public function isFileStore(): bool
    {
        return $this->driver() === 'file';
    }

    public function directory(): string
    {
        return (string) config(sprintf('cache.stores.%s.path', $this->store()), storage_path('framework/cache/data'));
    }

    /**
     * @param  int|null  $now  horodatage Unix de référence (tests) ; par défaut maintenant
     */
    public function run(?int $now = null): CachePurgeResult
    {
        if (! $this->isFileStore()) {
            return new CachePurgeResult($this->store(), $this->driver(), 0, 0, skipped: true);
        }

        $directory = $this->directory();

        if (! is_dir($directory)) {
            return new CachePurgeResult($this->store(), $this->driver(), 0, 0);
        }

        $now ??= time();
        $scanned = 0;
        $deleted = 0;

        foreach ($this->batches($directory) as $batch) {
            foreach ($batch as $path) {
                $scanned++;

                if ($this->isExpired($path, $now) && @unlink($path)) {
                    $deleted++;
                }
            }
        }

        Log::info('Maintenance : cache de fichiers purgé.', [
            'directory' => $directory,
            'scanned' => $scanned,
            'deleted' => $deleted,
        ]);

        return new CachePurgeResult($this->store(), $this->driver(), $scanned, $deleted);
    }

    /**
     * Un fichier de cache est expiré quand son en-tête (10 chiffres) est un horodatage déjà passé.
     */
    public function isExpired(string $path, ?int $now = null): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, self::HEADER_LENGTH);
        fclose($handle);

        if (! is_string($header) || strlen($header) !== self::HEADER_LENGTH || ! ctype_digit($header)) {
            return false;
        }

        return ($now ?? time()) >= (int) $header;
    }

    /**
     * Chemins des fichiers du répertoire, par lots de `BATCH_SIZE`, sans charger l'arborescence entière en mémoire.
     *
     * @return Generator<int, list<string>>
     */
    private function batches(string $directory): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $batch = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $batch[] = $file->getPathname();

            if (count($batch) >= self::BATCH_SIZE) {
                yield $batch;
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield $batch;
        }
    }
}
