<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\AppVersion;

/**
 * updates.json → app_versions (version, type → kind, notes, status « déployée » / « planifiée », date →
 * deployed_at ou scheduled_for). Déjà semées : réalignées par numéro de version ; une version déployée
 * par l'administration n'est jamais replanifiée.
 */
final class ImportAppVersions extends AbstractImporter
{
    public static function collection(): string
    {
        return 'updates';
    }

    protected function importRow(array $row, int $index): void
    {
        $number = $this->required($row['version'] ?? null, 'version', 20);

        if (preg_match(AppVersion::VERSION_PATTERN, $number) !== 1) {
            $this->reject(sprintf('numéro de version invalide « %s »', $number));
        }

        $status = AppVersion::statusFromPrototype((string) ($row['status'] ?? ''));
        $date = $this->ctx->date($row['date'] ?? null)?->setTime(8, 0);

        $version = AppVersion::query()->firstOrNew(['version' => $number]);

        $version->fill([
            'kind' => AppVersion::kindFromPrototype((string) ($row['type'] ?? '')),
            'notes' => $this->text($row['notes'] ?? null),
        ]);

        if (! $version->exists || ! $version->isDeployed()) {
            $version->fill([
                'status' => $status,
                'scheduled_for' => $status === AppVersion::STATUS_PLANNED ? $date : null,
                'deployed_at' => $status === AppVersion::STATUS_DEPLOYED ? $date : null,
            ]);
        }

        $version->save();
        $this->track($version);
    }
}
