<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Domain\Moderation\ReportPriority;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Models\ModerationReport;
use Illuminate\Database\Query\Builder;

/**
 * Calcule et range le score de priorité (P18-04, App\Domain\Moderation\ReportPriority) dans
 * `moderation_reports.priority`, pour **tous les signalements en attente d'un même contenu** (une ligne par contenu dans
 * la file, triée par ce score). Recalcul : à chaque nouveau signalement (ReportContent) et, après une décision, pour les
 * autres contenus en attente de la même personne (la récidive a pu changer, DecideReport). Récidive = contenus distincts
 * de la personne déjà sanctionnés (masqué, supprimé, avertissement, suspension, bannissement).
 */
final class ReportPrioritizer
{
    /** Actions qui comptent comme une sanction pour la récidive. */
    public const SANCTIONS = [
        ModerationReport::ACTION_HIDE,
        ModerationReport::ACTION_DELETE,
        ModerationReport::ACTION_WARN,
        ModerationReport::ACTION_SUSPEND,
        ModerationReport::ACTION_BAN,
    ];

    /** Recalcule le score des signalements en attente d'un contenu ; rend le score (0 sans signalement en attente). */
    public function refresh(string $subjectType, int $subjectId): int
    {
        $pending = ModerationReport::query()->pending()
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->toBase()->get(['id', 'kind', 'reason', 'reporter_user_id', 'reported_user_id']);

        $first = $pending->first();

        if ($first === null) {
            return 0;
        }

        $kind = ModerationKind::tryFrom((string) $first->kind) ?? ModerationKind::Post;
        $reasons = [];

        foreach ($pending as $row) {
            $reason = ContentReportReason::fromStored(is_string($row->reason) ? $row->reason : null);

            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        $people = $pending->pluck('reporter_user_id')->filter()->unique()->count();
        $author = $first->reported_user_id === null ? null : (int) $first->reported_user_id;

        $score = (new ReportPriority($reasons, $people, $kind, $author === null ? 0 : $this->sanctions($author, $subjectType, $subjectId)))->score();

        ModerationReport::query()->whereIn('id', $pending->pluck('id')->all())->update(['priority' => $score]);

        return $score;
    }

    /** Recalcule les contenus en attente d'une personne (après une décision : la récidive a changé). */
    public function refreshAuthor(int $userId): void
    {
        $subjects = ModerationReport::query()->pending()->where('reported_user_id', $userId)
            ->toBase()->distinct()->get(['subject_type', 'subject_id']);

        foreach ($subjects as $subject) {
            $this->refresh((string) $subject->subject_type, (int) $subject->subject_id);
        }
    }

    /** Recalcule toute la file en attente (commande `moderation:priorities`, rattrapage). */
    public function refreshAll(): int
    {
        $subjects = ModerationReport::query()->pending()->toBase()->distinct()->get(['subject_type', 'subject_id']);

        foreach ($subjects as $subject) {
            $this->refresh((string) $subject->subject_type, (int) $subject->subject_id);
        }

        return $subjects->count();
    }

    /** Contenus distincts de la personne déjà sanctionnés (hors le contenu examiné). */
    private function sanctions(int $userId, string $subjectType, int $subjectId): int
    {
        // Paires (nature, identifiant) distinctes, sans concaténation SQL propre à un moteur (SQLite en test, MySQL en production).
        return ModerationReport::query()->toBase()
            ->where('reported_user_id', $userId)
            ->whereIn('action', self::SANCTIONS)
            ->where(fn (Builder $query) => $query->where('subject_type', '!=', $subjectType)->orWhere('subject_id', '!=', $subjectId))
            ->distinct()
            ->get(['subject_type', 'subject_id'])
            ->count();
    }
}
