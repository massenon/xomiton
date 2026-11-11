<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Enums\AlertType;
use App\Enums\ContentEventKind;
use App\Models\Alert;
use App\Models\NewsletterSubscriber;
use App\Support\SmallCellMask;

/**
 * Tableau « Contenu » de l'écran Indicateurs (P10-06, CONTENT_STRATEGY.md §1) : l'audience des pages publiques mesurée
 * **sans traceur tiers**. Les clics (« Créer un compte », « Signaler », numéros d'urgence), les téléchargements et les
 * recherches sans résultat sont des compteurs serveur anonymes (ContentEvents, conservés 30 jours) ; les signalements
 * complétés, le délai déclenchement → accusé d'un SOS et les inscriptions newsletter confirmées se calculent sur les
 * tables existantes. Un effectif de 1 à 4 est masqué.
 */
final class ContentMetrics
{
    public const UNIT_COUNT = 'nb';

    public const UNIT_MINUTES = 'min';

    public function __construct(private readonly ContentEvents $events) {}

    /**
     * @return list<array{key: string, source: string, unit: string, value: int|null, masked: bool}>
     */
    public function rows(Period $period): array
    {
        $rows = [];

        foreach ([ContentEventKind::SignupClick, ContentEventKind::Download, ContentEventKind::ReportClick] as $kind) {
            $rows[] = $this->count($kind->value, 'counter', $this->events->total($kind, $period));
        }

        $rows[] = $this->count('report_completed', 'computed', Alert::query()
            ->whereIn('type', [AlertType::Report->value, AlertType::Need->value])
            ->whereBetween('created_at', [$period->startAt(), $period->endAt()])
            ->count());

        $rows[] = $this->sosDelay($period);

        foreach ([ContentEventKind::EmergencyCall, ContentEventKind::FaqNoResult] as $kind) {
            $rows[] = $this->count($kind->value, 'counter', $this->events->total($kind, $period));
        }

        $rows[] = $this->count('newsletter_confirmed', 'computed', NewsletterSubscriber::query()
            ->whereBetween('confirmed_at', [$period->startAt(), $period->endAt()])
            ->count());

        return $rows;
    }

    /**
     * @return array{key: string, source: string, unit: string, value: int|null, masked: bool}
     */
    private function count(string $key, string $source, int $count): array
    {
        return ['key' => $key, 'source' => $source, 'unit' => self::UNIT_COUNT, 'value' => SmallCellMask::mask($count), 'masked' => SmallCellMask::isMasked($count)];
    }

    /**
     * Délai médian, en minutes, entre le déclenchement d'un SOS et son accusé de réception, sur les SOS de la période
     * accusés (hors SOS annulés). Masqué sous 5 SOS accusés ; vide sans SOS accusé.
     *
     * @return array{key: string, source: string, unit: string, value: int|null, masked: bool}
     */
    private function sosDelay(Period $period): array
    {
        $delays = [];

        Alert::query()->sos()->whereNull('cancelled_at')->whereNotNull('acknowledged_at')
            ->whereBetween('created_at', [$period->startAt(), $period->endAt()])
            ->select(['id', 'created_at', 'acknowledged_at'])
            ->orderBy('id')
            ->lazyById(500)
            ->each(function (Alert $alert) use (&$delays): void {
                if ($alert->created_at !== null && $alert->acknowledged_at !== null) {
                    $delays[] = max(0, (int) round($alert->created_at->diffInSeconds($alert->acknowledged_at, true) / 60));
                }
            });

        $count = count($delays);

        if ($count === 0 || SmallCellMask::isMasked($count)) {
            return ['key' => 'sos_delay', 'source' => 'computed', 'unit' => self::UNIT_MINUTES, 'value' => null, 'masked' => $count > 0];
        }

        sort($delays);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1 ? $delays[$middle] : (int) round(($delays[$middle - 1] + $delays[$middle]) / 2);

        return ['key' => 'sos_delay', 'source' => 'computed', 'unit' => self::UNIT_MINUTES, 'value' => $median, 'masked' => false];
    }
}
