<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use App\Domain\Platform\Exceptions\BackupException;
use ZipArchive;

/**
 * Archive de sauvegarde ouverte en lecture (ZIP chiffré) : manifeste et morceaux de tables. À fermer après usage
 * (copie temporaire supprimée si l'archive vient d'un disque distant).
 */
final class BackupArchive
{
    /** @var array<string, mixed> */
    private array $manifest;

    private bool $closed = false;

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function __construct(private readonly ZipArchive $zip, array $manifest, private readonly ?string $temporary = null)
    {
        $this->manifest = $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return $this->manifest;
    }

    /**
     * Lignes d'un morceau de table (1, 2, …).
     *
     * @return list<array<string, mixed>>
     */
    public function part(string $table, int $part): array
    {
        $json = $this->zip->getFromName(BackupService::entry($table, $part));

        if ($json === false) {
            throw BackupException::unreadable();
        }

        $rows = json_decode($json, true);

        if (! is_array($rows)) {
            throw BackupException::unreadable();
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->zip->close();

        if ($this->temporary !== null && is_file($this->temporary)) {
            @unlink($this->temporary);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
