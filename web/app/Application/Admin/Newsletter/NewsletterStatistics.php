<?php

declare(strict_types=1);

namespace App\Application\Admin\Newsletter;

use App\Enums\SubscriberStatus;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterClick;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques de la newsletter (P14-02, `CONTENT_STRATEGY.md` §4, IND-09) : calculées sur les campagnes **envoyées**.
 * Ouverture = ouvertures uniques / délivrés, clic = personnes ayant cliqué / délivrés, désabonnement = désabonnés via la
 * campagne / délivrés, rebond = rebonds / envoyés (délivrés = envoyés − rebonds). Cibles : ouverture ≥ 35 %, clic ≥ 10 %,
 * désabonnement < 0,5 %, rebond < 3 % ; une valeur du mauvais côté de sa cible est signalée (`belowTarget`).
 * Aucune adresse ni nom dans ces calculs : uniquement des effectifs.
 */
final class NewsletterStatistics
{
    /** Cibles en pourcentage ; `min` : à atteindre, `max` : à ne pas atteindre. */
    public const TARGETS = [
        'open' => ['kind' => 'min', 'value' => 35.0],
        'click' => ['kind' => 'min', 'value' => 10.0],
        'unsubscribe' => ['kind' => 'max', 'value' => 0.5],
        'bounce' => ['kind' => 'max', 'value' => 3.0],
    ];

    public static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : 0.0;
    }

    /** Vrai quand le taux est du mauvais côté de sa cible (et qu'il existe des envois pour le juger). */
    public static function belowTarget(string $metric, float $rate, int $basis): bool
    {
        $target = self::TARGETS[$metric] ?? null;

        if ($target === null || $basis <= 0) {
            return false;
        }

        return $target['kind'] === 'min' ? $rate < $target['value'] : $rate >= $target['value'];
    }

    /**
     * Abonné·es par statut.
     *
     * @return array<string, int> valeur de SubscriberStatus => effectif
     */
    public function subscribersByStatus(): array
    {
        $counts = NewsletterSubscriber::query()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $result = [];

        foreach (SubscriberStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * Effectifs et taux de l'ensemble des campagnes envoyées.
     *
     * @return array{sent: int, delivered: int, opens: int, clickers: int, unsubscribes: int, bounces: int, open_rate: float, click_rate: float, unsubscribe_rate: float, bounce_rate: float}
     */
    public function totals(): array
    {
        $sum = ['sent' => 0, 'opens' => 0, 'clickers' => 0, 'unsubscribes' => 0, 'bounces' => 0];

        foreach ($this->aggregate(NewsletterCampaign::query()->sent()->pluck('id')->all()) as $row) {
            foreach ($sum as $key => $value) {
                $sum[$key] = $value + $row[$key];
            }
        }

        return self::withRates($sum);
    }

    /**
     * Une ligne par campagne envoyée (la plus récente d'abord).
     *
     * @return Collection<int, array{campaign: NewsletterCampaign, sent: int, delivered: int, opens: int, clickers: int, unsubscribes: int, bounces: int, open_rate: float, click_rate: float, unsubscribe_rate: float, bounce_rate: float}>
     */
    public function byCampaign(int $limit = 20): Collection
    {
        $campaigns = NewsletterCampaign::query()->sent()->orderByDesc('sent_at')->orderByDesc('id')->limit($limit)->get();
        $rows = $this->aggregate($campaigns->modelKeys());

        return $campaigns->map(static fn (NewsletterCampaign $campaign): array => ['campaign' => $campaign] + self::withRates($rows->get($campaign->getKey()) ?? ['sent' => 0, 'opens' => 0, 'clickers' => 0, 'unsubscribes' => 0, 'bounces' => 0]));
    }

    /**
     * Statistiques d'une campagne, quel que soit son état.
     *
     * @return array{sent: int, delivered: int, opens: int, clickers: int, unsubscribes: int, bounces: int, open_rate: float, click_rate: float, unsubscribe_rate: float, bounce_rate: float}
     */
    public function forCampaign(NewsletterCampaign $campaign): array
    {
        return self::withRates($this->aggregate([$campaign->getKey()])->get($campaign->getKey()) ?? ['sent' => 0, 'opens' => 0, 'clickers' => 0, 'unsubscribes' => 0, 'bounces' => 0]);
    }

    /**
     * Inscriptions et désinscriptions des 12 derniers mois (mois courant inclus).
     *
     * @return list<array{month: string, label: string, signups: int, unsubscribes: int}>
     */
    public function twelveMonths(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $months[$month->format('Y-m')] = ['month' => $month->format('Y-m'), 'label' => $month->format('m/y'), 'signups' => 0, 'unsubscribes' => 0];
        }

        NewsletterSubscriber::query()->toBase()
            ->where(fn ($q) => $q->where('created_at', '>=', $start)->orWhere('unsubscribed_at', '>=', $start))
            ->select(['created_at', 'unsubscribed_at'])
            ->orderBy('id')
            ->each(static function (object $row) use (&$months): void {
                $created = $row->created_at === null ? null : Carbon::parse($row->created_at)->format('Y-m');
                $left = $row->unsubscribed_at === null ? null : Carbon::parse($row->unsubscribed_at)->format('Y-m');

                if ($created !== null && isset($months[$created])) {
                    $months[$created]['signups']++;
                }

                if ($left !== null && isset($months[$left])) {
                    $months[$left]['unsubscribes']++;
                }
            });

        return array_values($months);
    }

    /**
     * Liens les plus cliqués (tous envois confondus).
     *
     * @return Collection<int, object{url: string, total: int, people: int}>
     */
    public function topLinks(int $limit = 8): Collection
    {
        return NewsletterClick::query()->toBase()
            ->selectRaw('url, SUM(clicks_count) as total, COUNT(*) as people')
            ->groupBy('url')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): object => (object) ['url' => (string) $row->url, 'total' => (int) $row->total, 'people' => (int) $row->people]);
    }

    /**
     * Centres d'intérêt des abonné·es actif·ves.
     *
     * @return array<string, int> libellé => effectif
     */
    public function interests(): array
    {
        $counts = DB::table('newsletter_subscriber_interests as si')
            ->join('newsletter_subscribers as s', 's.id', '=', 'si.subscriber_id')
            ->where('s.status', SubscriberStatus::Active->value)
            ->selectRaw('si.interest_id, COUNT(*) as n')
            ->groupBy('si.interest_id')
            ->pluck('n', 'interest_id');

        $result = [];

        foreach (NewsletterInterest::query()->ordered()->get() as $interest) {
            $result[(string) $interest->label] = (int) ($counts[$interest->getKey()] ?? 0);
        }

        return $result;
    }

    /**
     * @param  list<int|string>  $campaignIds
     * @return Collection<int|string, array{sent: int, opens: int, clickers: int, unsubscribes: int, bounces: int}>
     */
    private function aggregate(array $campaignIds): Collection
    {
        if ($campaignIds === []) {
            return collect();
        }

        return NewsletterCampaignRecipient::query()->toBase()
            ->whereIn('campaign_id', $campaignIds)
            ->selectRaw(
                'campaign_id,'
                .' SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,'
                .' SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,'
                .' SUM(CASE WHEN EXISTS (SELECT 1 FROM newsletter_clicks c WHERE c.recipient_id = newsletter_campaign_recipients.id) THEN 1 ELSE 0 END) as clickers,'
                .' SUM(CASE WHEN unsubscribed = 1 THEN 1 ELSE 0 END) as unsubscribes,'
                .' SUM(CASE WHEN bounced = 1 THEN 1 ELSE 0 END) as bounces'
            )
            ->groupBy('campaign_id')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [$row->campaign_id => [
                'sent' => (int) $row->sent, 'opens' => (int) $row->opens, 'clickers' => (int) $row->clickers,
                'unsubscribes' => (int) $row->unsubscribes, 'bounces' => (int) $row->bounces,
            ]]);
    }

    /**
     * @param  array{sent: int, opens: int, clickers: int, unsubscribes: int, bounces: int}  $row
     * @return array{sent: int, delivered: int, opens: int, clickers: int, unsubscribes: int, bounces: int, open_rate: float, click_rate: float, unsubscribe_rate: float, bounce_rate: float}
     */
    private static function withRates(array $row): array
    {
        $delivered = max(0, $row['sent'] - $row['bounces']);

        return $row + [
            'delivered' => $delivered,
            'open_rate' => self::rate($row['opens'], $delivered),
            'click_rate' => self::rate($row['clickers'], $delivered),
            'unsubscribe_rate' => self::rate($row['unsubscribes'], $delivered),
            'bounce_rate' => self::rate($row['bounces'], $row['sent']),
        ];
    }
}
