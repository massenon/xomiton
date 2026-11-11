<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\AuditLog;

/**
 * journal.json → audit_logs (at → created_at, user e-mail → user_id quand le compte existe + actor
 * (e-mail ou « anonyme »), action, detail). Le journal est immuable : une entrée déjà présente
 * (même horodatage, acteur, action et détail) n'est ni dupliquée ni modifiée.
 */
final class ImportAuditLogs extends AbstractImporter
{
    public static function collection(): string
    {
        return 'journal';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $createdAt = $this->ctx->dateTime($row['at'] ?? null) ?? $this->reject('horodatage « at » absent ou invalide');
        $action = $this->required($row['action'] ?? null, 'action', 80);
        $actor = $this->text($row['user'] ?? null, 190) ?? AuditLog::ACTOR_ANONYMOUS;
        $actor = str_contains($actor, '@') ? mb_strtolower($actor) : $actor;
        $detail = $this->text($row['detail'] ?? null);
        $userId = str_contains($actor, '@') ? $this->ctx->userIdByEmail($actor) : null;

        $exists = AuditLog::query()
            ->where('created_at', $createdAt)
            ->where('action', $action)
            ->where('actor', $actor)
            ->where('detail', $detail)
            ->exists();

        if ($exists) {
            $this->ctx->report->updated(self::collection());

            return;
        }

        $log = new AuditLog([
            'user_id' => $userId,
            'actor' => $actor,
            'action' => $action,
            'detail' => $detail,
        ]);
        $log->created_at = $createdAt;
        $log->save();

        $this->track($log);
    }
}
