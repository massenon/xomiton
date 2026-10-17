<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\SyncDevice;
use App\Models\User;

/**
 * sync_devices.json → sync_devices (name « Prénom I. — Commune » → compte pair·e dont le nom commence par
 * « Prénom I », sinon rejet journalisé ; device → platform ; last → last_seen_at / last_sync_at ;
 * pending → pending_count ; conflicts → conflicts_count). Idempotence : device_uuid dérivé de l'identifiant
 * du prototype ; le libellé complet sert de clé aux conflits (sync_conflicts.from).
 */
final class ImportSyncDevices extends AbstractImporter
{
    public static function collection(): string
    {
        return 'sync_devices';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $label = $this->required($row['name'] ?? $row['label'] ?? null, 'name', 80);
        $userId = $this->userIdForLabel($label);
        $lastSeen = $this->ctx->dateTime($row['last'] ?? null);

        $device = SyncDevice::query()->updateOrCreate(
            ['device_uuid' => ImportKeys::uuid('sync_devices', $this->prototypeId($row, $index))],
            [
                'user_id' => $userId,
                'label' => $label,
                'platform' => $this->text($row['device'] ?? $row['platform'] ?? null, 40),
                'last_seen_at' => $lastSeen,
                'last_sync_at' => $lastSeen,
                'pending_count' => max(0, (int) ($row['pending'] ?? 0)),
                'conflicts_count' => max(0, (int) ($row['conflicts'] ?? 0)),
                'is_revoked' => false,
            ],
        );

        $this->ctx->remember('devices', $label, (int) $device->getKey());
        $this->track($device);
    }

    /**
     * « Esther K. — Parakou » → compte unique dont le nom normalisé commence par « esther k ».
     */
    private function userIdForLabel(string $label): int
    {
        $person = trim((string) preg_split('/\s*[–—-]\s*/u', $label)[0]);
        $prefix = ImportKeys::normalizeName(rtrim($person, '.'));

        if ($prefix === '' || ! str_contains($prefix, ' ')) {
            $this->reject(sprintf('libellé « %s » sans personne identifiable', $label));
        }

        $matches = [];

        foreach (User::query()->whereNotNull('name')->pluck('name', 'id') as $id => $name) {
            if (str_starts_with(ImportKeys::normalizeName((string) $name), $prefix)) {
                $matches[] = (int) $id;
            }
        }

        if ($matches === []) {
            $this->reject(sprintf('aucun compte ne correspond à « %s »', $person));
        }

        if (count($matches) > 1) {
            $this->reject(sprintf('plusieurs comptes correspondent à « %s »', $person));
        }

        return $matches[0];
    }
}
