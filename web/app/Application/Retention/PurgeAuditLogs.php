<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Retention\Concerns\DeletesInChunks;
use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Règle de rétention du journal d'audit (DATABASE_ENGINEERING.md §6 : 90 jours, configurable).
 * La durée vient, par ordre de priorité, de l'argument explicite, du paramètre d'administration
 * `audit_log_days` (écran Journal, P13-05) puis de `xomiton.retention.audit_log_days`.
 */
final class PurgeAuditLogs
{
    use DeletesInChunks;

    /** Clé du paramètre d'administration modifiable depuis l'écran Journal. */
    public const SETTING_KEY = 'audit_log_days';

    /** Bornes admises (P13-05 : 7 à 3650 jours) : garde-fou contre une purge totale accidentelle. */
    public const MIN_DAYS = 7;

    public const MAX_DAYS = 3650;

    /**
     * Durée de rétention effective, en jours.
     *
     * @throws InvalidArgumentException si la durée est hors bornes.
     */
    public function resolveDays(?int $days = null): int
    {
        $days ??= Settings::has(self::SETTING_KEY)
            ? Settings::int(self::SETTING_KEY, (int) config('xomiton.retention.audit_log_days', 90))
            : (int) config('xomiton.retention.audit_log_days', 90);

        if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
            throw new InvalidArgumentException(sprintf(
                'La rétention du journal d’audit doit être comprise entre %d et %d jours (%d demandés).',
                self::MIN_DAYS,
                self::MAX_DAYS,
                $days,
            ));
        }

        return $days;
    }

    /**
     * @return int Nombre d'entrées supprimées.
     */
    public function run(?int $days = null): int
    {
        $days = $this->resolveDays($days);

        $deleted = $this->deleteInChunks(AuditLog::query()->olderThan($days));

        Log::info('Rétention : journal d’audit purgé.', ['count' => $deleted, 'days' => $days]);

        return $deleted;
    }
}
