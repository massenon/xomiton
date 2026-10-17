<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Retention\Concerns\DeletesInChunks;
use App\Models\MailLog;
use Illuminate\Support\Facades\Log;

/**
 * Règle de rétention des journaux d'envoi d'e-mails (DATABASE_ENGINEERING.md §6 : 180 jours) :
 * supprime les lignes de `mail_logs` au-delà de `MailLog::RETENTION_DAYS` (scope `expired`).
 */
final class PurgeMailLogs
{
    use DeletesInChunks;

    public function days(): int
    {
        return MailLog::RETENTION_DAYS;
    }

    /**
     * @return int Nombre de lignes supprimées.
     */
    public function run(): int
    {
        $deleted = $this->deleteInChunks(MailLog::query()->expired());

        Log::info('Rétention : journaux d’envoi purgés.', ['count' => $deleted, 'days' => $this->days()]);

        return $deleted;
    }
}
