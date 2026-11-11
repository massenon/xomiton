<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Retention\Concerns\DeletesInChunks;
use App\Models\MailLog;
use App\Models\MailOutbox;
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
     * Q57 : les e-mails en échec ne gardent leur contenu que `xomiton.retention.mail_outbox_failed_days` jours ;
     * les lignes envoyées (déjà vidées) suivent la durée des journaux.
     *
     * @return int Nombre de lignes supprimées.
     */
    public function purgeOutbox(): int
    {
        $failedDays = max(1, (int) config('xomiton.retention.mail_outbox_failed_days', 7));

        return MailOutbox::query()->whereNull('sent_at')->where('created_at', '<', now()->subDays($failedDays))->delete()
            + MailOutbox::query()->whereNotNull('sent_at')->where('sent_at', '<', now()->subDays($this->days()))->delete();
    }

    /**
     * @return int Nombre de lignes supprimées.
     */
    public function run(): int
    {
        $deleted = $this->deleteInChunks(MailLog::query()->expired());
        $this->purgeOutbox();

        Log::info('Rétention : journaux d’envoi purgés.', ['count' => $deleted, 'days' => $this->days()]);

        return $deleted;
    }
}
