<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Models\CaseFollowup;
use App\Rules\NotFutureDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tableau de bord du suivi des victimes de l'administration (P11-03 ; prototype admin/suivi.php l.43-60) : KPI, étapes en
 * retard et prochains suivis, sur les étapes des dossiers **non archivés** (un dossier archivé quitte listes et KPI).
 *
 *  - suivis réalisés : part des étapes dont le résultat est « réalisé » ;
 *  - en retard : étape encore à faire (planifiée ou reportée) dont la prochaine échéance (`next_on`) est dépassée,
 *    au jour calendaire du Bénin ;
 *  - délai moyen entre suivis : moyenne, en jours, de `next_on` − `date` sur les étapes qui annoncent une suite ;
 *  - premier suivi : délai moyen, en jours, entre la date des faits et la première étape de chaque dossier ;
 *  - dossiers suivis, et suivis à venir (échéance à partir d'aujourd'hui, étape encore à faire).
 * Aucune colonne chiffrée (commentaire) n'est lue : seules les dates, le résultat et la nature de l'étape.
 */
final class FollowupBoard
{
    /**
     * @return array{total: int, done: int, percent: int, overdue: int, upcoming: int, gap_days: int, first_days: int, cases: int}
     */
    public function kpis(): array
    {
        $today = self::today();
        $total = $this->steps()->count();
        $done = $this->steps()->where('case_followups.outcome', FollowupOutcome::Done->value)->count();
        $overdue = $this->overdue()->count();
        $upcoming = $this->upcoming()->count();

        $gaps = [];

        foreach ($this->steps()->whereNotNull('case_followups.next_on')->select(['case_followups.date', 'case_followups.next_on'])->toBase()->cursor() as $row) {
            $gaps[] = CarbonImmutable::parse((string) $row->date)->diffInDays(CarbonImmutable::parse((string) $row->next_on), false);
        }

        $firsts = [];
        $rows = $this->steps()
            ->toBase()
            ->selectRaw('cases.id as case_id, cases.occurred_on as occurred_on, MIN(case_followups.date) as first_date')
            ->groupBy('cases.id', 'cases.occurred_on')
            ->get();

        foreach ($rows as $row) {
            $firsts[] = max(0, CarbonImmutable::parse((string) $row->occurred_on)->diffInDays(CarbonImmutable::parse((string) $row->first_date), false));
        }

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total === 0 ? 0 : (int) round($done * 100 / $total),
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'gap_days' => $gaps === [] ? 0 : (int) round(array_sum($gaps) / count($gaps)),
            'first_days' => $firsts === [] ? 0 : (int) round(array_sum($firsts) / count($firsts)),
            'cases' => count($rows),
        ];
    }

    /**
     * Étapes à faire dont la prochaine échéance est dépassée, la plus ancienne d'abord.
     *
     * @return Builder<CaseFollowup>
     */
    public function overdue(): Builder
    {
        return $this->steps()->pending()->whereNotNull('case_followups.next_on')->where('case_followups.next_on', '<', self::today()->format('Y-m-d'));
    }

    /**
     * Étapes à faire avec une échéance à partir d'aujourd'hui.
     *
     * @return Builder<CaseFollowup>
     */
    public function upcoming(): Builder
    {
        return $this->steps()->pending()->whereNotNull('case_followups.next_on')->where('case_followups.next_on', '>=', self::today()->format('Y-m-d'));
    }

    /**
     * Étapes en retard ou à venir pour les encarts latéraux (code du dossier et auteur·e chargés, pas de commentaire).
     *
     * @return Collection<int, CaseFollowup>
     */
    public function sidebar(bool $late, int $limit = 8): Collection
    {
        $query = $late ? $this->overdue() : $this->upcoming();

        return $query->with(['case:id,code', 'author:id,pseudo'])
            ->select('case_followups.*')
            ->orderBy('case_followups.next_on')->orderBy('case_followups.id')
            ->limit($limit)->get();
    }

    /**
     * Étapes des dossiers non archivés (jointure de lecture seule sur `cases`).
     *
     * @return Builder<CaseFollowup>
     */
    public function steps(): Builder
    {
        return CaseFollowup::query()
            ->join('cases', 'cases.id', '=', 'case_followups.case_id')
            ->where('cases.status', '!=', CaseStatus::Archived->value);
    }

    /** Une étape annoncée « en retard » : encore à faire et échéance dépassée. */
    public static function isLate(CaseFollowup $followup): bool
    {
        return $followup->outcome->isPending()
            && $followup->next_on !== null
            && $followup->next_on->format('Y-m-d') < self::today()->format('Y-m-d');
    }

    private static function today(): CarbonImmutable
    {
        return NotFutureDate::today();
    }
}
