<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\FollowupOutcome;
use App\Models\CaseFollowup;

/**
 * suivi.json → case_followups (case_code → case_id via la correspondance des codes, by → by_user_id,
 * outcome « En attente » → planifie, next → next_on, comment chiffré). Idempotence : client_uuid
 * dérivé de l'identifiant du prototype.
 */
final class ImportFollowups extends AbstractImporter
{
    public static function collection(): string
    {
        return 'suivi';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $code = $this->required($row['case_code'] ?? null, 'case_code', 10);
        $caseId = $this->ctx->caseIdByPrototypeCode($code) ?? $this->reject(sprintf('dossier « %s » inconnu', $code));
        $date = $this->ctx->date($row['date'] ?? null) ?? $this->reject('date de l’étape absente ou invalide');
        $step = $this->required($row['step'] ?? null, 'step', 80);

        $byName = $this->text($row['by'] ?? null);
        $byUserId = $this->ctx->userIdByName($byName);

        if ($byName !== null && $byUserId === null) {
            $this->ctx->note(sprintf('suivi #%s : pair·e « %s » inconnu·e, étape importée sans auteur·rice', (string) ($row['id'] ?? $index + 1), $byName));
        }

        $followup = CaseFollowup::query()->updateOrCreate(
            ['client_uuid' => ImportKeys::uuid('suivi', $this->prototypeId($row, $index))],
            [
                'case_id' => $caseId,
                'date' => $date,
                'step' => $step,
                'by_user_id' => $byUserId,
                'outcome' => FollowupOutcome::fromPrototype((string) ($row['outcome'] ?? '')),
                'next_on' => $this->ctx->date($row['next'] ?? null),
                'comment' => $this->text($row['comment'] ?? null, 2000),
            ],
        );

        $this->track($followup);
    }
}
