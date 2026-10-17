<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Models\ModerationReport;

/**
 * moderation_reports.json → moderation_reports (kind typé, target texte → excerpt, user_id du prototype →
 * reported_user_id, reporter texte → reporter_label (+ reporter_user_id si un compte porte ce nom ou ce
 * pseudonyme), reason, status, action vide → null, created → created_at). La cible du prototype n'étant
 * qu'un texte, `subject_type` est la classe de la nature et `subject_id` vaut l'identifiant du compte
 * signalé pour un·e utilisateur·rice, 0 sinon (contenu sans équivalent en base).
 * Clé naturelle : nature, compte signalé, motif et horodatage.
 */
final class ImportModerationReports extends AbstractImporter
{
    public static function collection(): string
    {
        return 'moderation_reports';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $kind = ModerationKind::tryFrom((string) ($row['kind'] ?? ''))
            ?? $this->reject(sprintf('nature inconnue « %s »', (string) ($row['kind'] ?? '')));
        $status = ModerationStatus::tryFrom((string) ($row['status'] ?? 'en_attente'))
            ?? $this->reject(sprintf('statut inconnu « %s »', (string) ($row['status'] ?? '')));
        $reason = $this->required($row['reason'] ?? null, 'reason', 80);
        $createdAt = $this->ctx->dateTime($row['created'] ?? null) ?? $this->reject('horodatage « created » absent ou invalide');

        $reportedPrototypeId = (int) ($row['user_id'] ?? 0);
        $reportedUserId = $reportedPrototypeId > 0 ? $this->ctx->userId($reportedPrototypeId) : null;

        if ($reportedPrototypeId > 0 && $reportedUserId === null) {
            $this->ctx->note(sprintf('signalement #%s : compte signalé #%d inconnu', (string) $this->prototypeId($row, $index), $reportedPrototypeId));
        }

        $reporterLabel = $this->text($row['reporter'] ?? null, 80);
        $action = $this->text($row['action'] ?? null, 40);

        if ($action !== null && ! in_array($action, ModerationReport::ACTIONS, true)) {
            $this->reject(sprintf('action inconnue « %s »', $action));
        }

        $report = ModerationReport::query()->firstOrNew([
            'kind' => $kind->value,
            'reported_user_id' => $reportedUserId,
            'reason' => $reason,
            'created_at' => $createdAt,
        ]);

        $report->fill([
            'subject_type' => $kind->subjectClass(),
            'subject_id' => $kind === ModerationKind::User ? ($reportedUserId ?? 0) : 0,
            'excerpt' => $this->text($row['target'] ?? null, 255),
            'reporter_user_id' => $this->ctx->userIdByName($reporterLabel) ?? $this->ctx->userIdByPseudo($reporterLabel),
            'reporter_label' => $reporterLabel,
            'details' => $this->text($row['details'] ?? null, 500),
            'status' => $status,
            'action' => $action,
            'handled_at' => $status === ModerationStatus::Pending ? null : ($report->handled_at ?? $createdAt),
        ]);
        $report->created_at = $createdAt;
        $report->save();

        $this->track($report);
    }
}
