<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Enums\AlertStatus;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\Event;
use App\Models\NewsletterCampaignRecipient;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Calcul des indicateurs intégrés (`indicators.calc_key`, database/seeders/data/steering/indicators.php) sur une
 * période, au niveau national (`$zone` null) ou par département. Une méthode par clé de calcul ; les clés sans
 * calcul possible sur les tables actuelles sont listées dans `UNAVAILABLE` avec leur raison.
 *
 * Conventions : les dossiers, alertes et suivis sont rattachés à la période par leur date d'enregistrement
 * (`created_at`, `case_followups.date`, `events.starts_at`, `newsletter_campaigns.sent_at`) ; les stocks
 * (comptes actifs) sont mesurés au moment du calcul.
 */
final class IndicatorCalculator
{
    /** Délai de traitement d'une alerte pour IND-10 (heures). */
    public const ALERT_HANDLING_HOURS = 24;

    /** Délai de première prise en charge d'un dossier pour IND-04 (heures). */
    public const FIRST_SERVICE_HOURS = 72;

    /**
     * calc_key => [méthode, calcul par zone ?].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    private const CALCULATIONS = [
        'cas_total' => ['casTotal', true],
        'cas_vbg' => ['casVbg', true],
        'cas_vdh' => ['casVdh', true],
        'pct_72h' => ['pct72h', true],
        'pct_orientes' => ['pctOrientes', true],
        'pct_clos' => ['pctClos', true],
        'pairs' => ['pairs', true],
        'seances' => ['seances', false],
        'nl_open' => ['nlOpen', false],
        'pct_alertes24' => ['pctAlertes24', true],
        'alertes' => ['alertes', true],
        'sos' => ['sos', true],
        'pct_suivis' => ['pctSuivis', true],
        'suivies' => ['suivies', true],
        'users' => ['users', true],
        'couverture' => ['couverture', false],
    ];

    /**
     * calc_key => raison pour laquelle le calcul n'est pas possible sur les tables existantes (aucune à ce jour :
     * IND-04 se calcule sur le premier `case_followups.date`, P10-06).
     *
     * @var array<string, string>
     */
    private const UNAVAILABLE = [];

    /**
     * Clés calculables.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::CALCULATIONS);
    }

    public function supports(string $calcKey): bool
    {
        return array_key_exists($calcKey, self::CALCULATIONS);
    }

    /**
     * L'indicateur se calcule-t-il aussi par département ?
     */
    public function isZonal(string $calcKey): bool
    {
        return self::CALCULATIONS[$calcKey][1] ?? false;
    }

    /**
     * Raison de l'impossibilité de calcul (clé connue mais non calculable, ou clé inconnue).
     */
    public function unavailableReason(string $calcKey): string
    {
        return self::UNAVAILABLE[$calcKey] ?? sprintf('clé de calcul « %s » inconnue d’IndicatorCalculator', $calcKey);
    }

    /**
     * @throws InvalidArgumentException clé non calculable.
     */
    public function compute(string $calcKey, Period $period, ?Zone $zone = null): IndicatorComputation
    {
        if (! $this->supports($calcKey)) {
            throw new InvalidArgumentException($this->unavailableReason($calcKey));
        }

        if ($zone !== null && ! $this->isZonal($calcKey)) {
            return IndicatorComputation::unavailable('indicateur national uniquement');
        }

        $method = self::CALCULATIONS[$calcKey][0];

        return $this->{$method}($period, $zone);
    }

    // -- Registres (IND-01 à IND-03, IND-05 à IND-07, IND-16) --------------------------------------

    /** IND-01 : dossiers enregistrés sur la période, hors archivés. */
    private function casTotal(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of(
            $this->cases($period, $zone)->where('status', '!=', CaseStatus::Archived->value)->count()
        );
    }

    /** IND-02 : dossiers VBG enregistrés sur la période, hors archivés. */
    private function casVbg(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of(
            $this->cases($period, $zone)->ofKind(CaseKind::Vbg)->where('status', '!=', CaseStatus::Archived->value)->count()
        );
    }

    /** IND-03 : dossiers VDH enregistrés sur la période, hors archivés. */
    private function casVdh(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of(
            $this->cases($period, $zone)->ofKind(CaseKind::Vdh)->where('status', '!=', CaseStatus::Archived->value)->count()
        );
    }

    /**
     * IND-04 : part des dossiers dont la première étape de suivi **réalisée** (`case_followups.date`, une date locale sans
     * heure ; une étape planifiée, reportée ou annulée n'est pas une prise en charge) tombe au plus tard 72 h après
     * l'enregistrement du dossier (`cases.created_at` + 72 h, ramené au fuseau d'affichage avant de ne garder que la
     * date). Un dossier sans étape réalisée compte au dénominateur et pas au numérateur. Les dossiers archivés restent
     * dans la base (comme pour IND-05 et IND-06) : seuls IND-01 à IND-03 les excluent (Q90).
     */
    private function pct72h(Period $period, ?Zone $zone): IndicatorComputation
    {
        $cases = $this->cases($period, $zone)
            ->select(['cases.id', 'cases.created_at'])
            ->selectSub(
                CaseFollowup::query()->selectRaw('MIN(case_followups.date)')->whereColumn('case_followups.case_id', 'cases.id')
                    ->where('case_followups.outcome', FollowupOutcome::Done->value),
                'first_followup_on',
            );

        $total = 0;
        $within = 0;

        foreach ($cases->lazyById(500, 'cases.id', 'id') as $case) {
            $total++;

            /** @var string|null $firstFollowup */
            $firstFollowup = $case->getAttribute('first_followup_on');

            if ($firstFollowup !== null && $case->created_at !== null
                && substr($firstFollowup, 0, 10) <= $case->created_at->copy()->addHours(self::FIRST_SERVICE_HOURS)->setTimezone((string) config('xomiton.display_timezone', 'UTC'))->toDateString()) {
                $within++;
            }
        }

        return IndicatorComputation::percentage($within, $total, 'aucun dossier enregistré sur la période');
    }

    /**
     * IND-05 : part des dossiers orientés (service d'orientation `cases.referral` ou orientation d'une fiche
     * vers un contact ou un libellé de l'annuaire).
     */
    private function pctOrientes(Period $period, ?Zone $zone): IndicatorComputation
    {
        $total = $this->cases($period, $zone)->count();

        $oriented = $this->cases($period, $zone)
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $q) => $q->whereNotNull('referral')->where('referral', '!=', ''))
                    ->orWhereHas('forms', function (Builder $form): void {
                        $form->where(fn (Builder $q) => $q->whereNotNull('referral_contact_id')
                            ->orWhere(fn (Builder $label) => $label->whereNotNull('referral_label')->where('referral_label', '!=', '')));
                    });
            })
            ->count();

        return IndicatorComputation::percentage($oriented, $total, 'aucun dossier enregistré sur la période');
    }

    /** IND-06 : part des dossiers clos ou archivés parmi ceux enregistrés sur la période. */
    private function pctClos(Period $period, ?Zone $zone): IndicatorComputation
    {
        $total = $this->cases($period, $zone)->count();
        $closed = $this->cases($period, $zone)
            ->whereIn('status', [CaseStatus::Closed->value, CaseStatus::Archived->value])
            ->count();

        return IndicatorComputation::percentage($closed, $total, 'aucun dossier enregistré sur la période');
    }

    /** IND-07 : pair·es distinct·es ayant enregistré au moins un dossier sur la période. */
    private function pairs(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of($this->cases($period, $zone)->distinct()->count('collected_by'));
    }

    /** IND-16 : part des départements actifs ayant au moins un dossier enregistré sur la période. */
    private function couverture(Period $period, ?Zone $zone): IndicatorComputation
    {
        $zones = Zone::query()->active()->count();
        $covered = $this->cases($period, null)
            ->whereIn('zone_id', Zone::query()->active()->select('id'))
            ->distinct()
            ->count('zone_id');

        return IndicatorComputation::percentage($covered, $zones, 'aucun département actif dans le référentiel');
    }

    // -- Réseau social et newsletter (IND-08, IND-09) ------------------------------------------------

    /** IND-08 : événements publiés qui commencent sur la période (causeries, ateliers, séances CCC). */
    private function seances(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of(
            Event::query()->published()->whereBetween('starts_at', [$period->startAt(), $period->endAt()])->count()
        );
    }

    /**
     * IND-09 : ouvertures uniques / e-mails délivrés × 100, pour les campagnes envoyées sur la période
     * (destinataires effectivement envoyés et non rebondis).
     */
    private function nlOpen(Period $period, ?Zone $zone): IndicatorComputation
    {
        $delivered = NewsletterCampaignRecipient::query()
            ->whereHas('campaign', fn (Builder $campaign) => $campaign->whereBetween('sent_at', [$period->startAt(), $period->endAt()]))
            ->whereNotNull('sent_at')
            ->where('bounced', false);

        $sent = $delivered->clone()->count();
        $opened = $delivered->clone()->whereNotNull('opened_at')->count();

        return IndicatorComputation::percentage($opened, $sent, 'aucun e-mail de campagne envoyé sur la période');
    }

    // -- Alertes (IND-10 à IND-12) ---------------------------------------------------------------------

    /**
     * IND-10 : part des alertes reçues sur la période (hors alertes annulées, `cancelled_at` : une alerte annulée n'a
     * rien à traiter, Q90) passées à « traitée » ou « close » dans les 24 h : premier changement de statut correspondant (`alert_status_changes`), sinon `closed_at`.
     */
    private function pctAlertes24(Period $period, ?Zone $zone): IndicatorComputation
    {
        $handledStatuses = [AlertStatus::Handled->value, AlertStatus::Closed->value];

        $alerts = $this->alerts($period, $zone)
            ->whereNull('cancelled_at')
            ->select(['alerts.id', 'alerts.created_at', 'alerts.closed_at'])
            ->selectSub(
                AlertStatusChange::query()
                    ->selectRaw('MIN(created_at)')
                    ->whereColumn('alert_id', 'alerts.id')
                    ->whereIn('to_status', $handledStatuses),
                'first_handled_at',
            );

        $total = 0;
        $within = 0;

        foreach ($alerts->lazyById(500, 'alerts.id', 'id') as $alert) {
            $total++;

            /** @var string|null $firstHandled */
            $firstHandled = $alert->getAttribute('first_handled_at');
            $handledAt = $firstHandled !== null ? CarbonImmutable::parse($firstHandled) : $alert->closed_at;

            if ($handledAt !== null && $alert->created_at !== null
                && $handledAt->lessThanOrEqualTo($alert->created_at->copy()->addHours(self::ALERT_HANDLING_HOURS))) {
                $within++;
            }
        }

        return IndicatorComputation::percentage($within, $total, 'aucune alerte reçue sur la période');
    }

    /** IND-11 : dénonciations, besoins et SOS reçus sur la période. */
    private function alertes(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of($this->alerts($period, $zone)->count());
    }

    /**
     * IND-12 : SOS reçus sur la période avec une localisation GPS. Seule la présence de la position est testée
     * (`whereNotNull('lat')`) : la colonne est chiffrée, sa valeur n'est jamais lue ni comparée (ADR-33).
     */
    private function sos(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of($this->alerts($period, $zone)->sos()->whereNotNull('lat')->count());
    }

    // -- Suivi (IND-13, IND-14) ----------------------------------------------------------------------

    /** IND-13 : étapes de suivi réalisées / étapes datées sur la période × 100. */
    private function pctSuivis(Period $period, ?Zone $zone): IndicatorComputation
    {
        $total = $this->followups($period, $zone)->count();
        $done = $this->followups($period, $zone)->where('case_followups.outcome', FollowupOutcome::Done->value)->count();

        return IndicatorComputation::percentage($done, $total, 'aucune étape de suivi datée sur la période');
    }

    /** IND-14 : dossiers distincts ayant au moins une étape de suivi datée sur la période. */
    private function suivies(Period $period, ?Zone $zone): IndicatorComputation
    {
        return IndicatorComputation::of($this->followups($period, $zone)->distinct()->count('case_followups.case_id'));
    }

    // -- Utilisateur·rices (IND-15) ------------------------------------------------------------------

    /** IND-15 : comptes au statut actif (stock mesuré au moment du calcul, non supprimés). */
    private function users(Period $period, ?Zone $zone): IndicatorComputation
    {
        $query = User::query()->active();

        if ($zone !== null) {
            $query->inZone((int) $zone->getKey());
        }

        return IndicatorComputation::of($query->count());
    }

    // -- Requêtes de base ----------------------------------------------------------------------------

    /**
     * @return Builder<CaseRecord>
     */
    private function cases(Period $period, ?Zone $zone): Builder
    {
        $query = CaseRecord::query()->whereBetween('cases.created_at', [$period->startAt(), $period->endAt()]);

        if ($zone !== null) {
            $query->inZone($zone);
        }

        return $query;
    }

    /**
     * @return Builder<Alert>
     */
    private function alerts(Period $period, ?Zone $zone): Builder
    {
        $query = Alert::query()->whereBetween('alerts.created_at', [$period->startAt(), $period->endAt()]);

        if ($zone !== null) {
            $query->inZone($zone);
        }

        return $query;
    }

    /**
     * Étapes de suivi datées sur la période, rattachées au département du dossier.
     *
     * @return Builder<CaseFollowup>
     */
    private function followups(Period $period, ?Zone $zone): Builder
    {
        $query = CaseFollowup::query()
            ->whereBetween('case_followups.date', [$period->start->toDateString(), $period->end->toDateString()]);

        if ($zone !== null) {
            $query->whereHas('case', fn (Builder $case) => $case->inZone($zone));
        }

        return $query;
    }
}
