<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Retention\Concerns\DeletesInChunks;
use App\Models\SyncDevice;
use App\Models\SyncOutboxReceipt;
use Illuminate\Support\Facades\Log;

/**
 * Nettoyage de la synchronisation hors connexion (P1-16 `sync:prune`) :
 * - les accusés de réception `sync_outbox_receipts` plus vieux que `xomiton.retention.sync_receipt_days` sont supprimés
 *   (la fenêtre de rejeu d'un lot est largement dépassée) ;
 * - les appareils sans contact depuis plus de `xomiton.retention.device_inactive_days` sont révoqués (`is_revoked`),
 *   jamais supprimés : des alertes et des dossiers les référencent.
 */
final class PruneSyncData
{
    use DeletesInChunks;

    public function receiptDays(): int
    {
        return (int) config('xomiton.retention.sync_receipt_days', 90);
    }

    public function deviceDays(): int
    {
        return (int) config('xomiton.retention.device_inactive_days', 180);
    }

    public function run(): SyncPruneResult
    {
        $receipts = $this->deleteInChunks(SyncOutboxReceipt::query()->olderThan($this->receiptDays()));

        $devices = SyncDevice::query()
            ->active()
            ->inactiveForDays($this->deviceDays())
            ->update(['is_revoked' => true, 'updated_at' => now()]);

        Log::info('Rétention : synchronisation nettoyée.', [
            'receipts_deleted' => $receipts,
            'devices_revoked' => $devices,
        ]);

        return new SyncPruneResult($receipts, $devices, $this->receiptDays(), $this->deviceDays());
    }
}
