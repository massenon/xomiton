<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\ConflictStatus;
use App\Models\CaseRecord;
use App\Models\SyncConflict;

/**
 * sync_conflicts.json → sync_conflicts (code → subject cases via la correspondance des codes, from →
 * device_id par libellé d'appareil, field, server / device → valeurs, status « à résoudre » / « résolu »,
 * resolution vide → null, at → created_at). Clé naturelle : dossier, champ et horodatage.
 */
final class ImportSyncConflicts extends AbstractImporter
{
    public static function collection(): string
    {
        return 'sync_conflicts';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $code = $this->required($row['code'] ?? null, 'code', 10);
        $caseId = $this->ctx->caseIdByPrototypeCode($code) ?? $this->reject(sprintf('dossier « %s » inconnu', $code));
        $field = $this->required($row['field'] ?? null, 'field', 40);
        $createdAt = $this->ctx->dateTime($row['at'] ?? null) ?? $this->reject('horodatage « at » absent ou invalide');

        $deviceLabel = $this->text($row['from'] ?? null, 80);
        $deviceId = $this->ctx->deviceIdByLabel($deviceLabel);

        if ($deviceLabel !== null && $deviceId === null) {
            $this->ctx->note(sprintf('conflit #%s : appareil « %s » inconnu, conflit importé sans appareil', (string) $this->prototypeId($row, $index), $deviceLabel));
        }

        $resolution = $this->text($row['resolution'] ?? null, 10);

        if ($resolution !== null && ! in_array($resolution, SyncConflict::RESOLUTIONS, true)) {
            $this->reject(sprintf('résolution inconnue « %s »', $resolution));
        }

        $status = ConflictStatus::fromPrototype((string) ($row['status'] ?? ''));

        $conflict = SyncConflict::query()->firstOrNew([
            'subject_type' => (new CaseRecord)->getMorphClass(),
            'subject_id' => $caseId,
            'field' => $field,
            'created_at' => $createdAt,
        ]);

        $conflict->fill([
            'device_id' => $deviceId,
            'server_value' => $this->text($row['server'] ?? null),
            'device_value' => $this->text($row['device'] ?? null),
            'status' => $status,
            'resolution' => $resolution,
            'resolved_at' => $status->isOpen() ? null : ($conflict->resolved_at ?? $createdAt),
        ]);
        $conflict->created_at = $createdAt;
        $conflict->save();

        $this->track($conflict);
    }
}
