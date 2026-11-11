<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Instantanés des indicateurs intégrés (`indicators:snapshot`, DATABASE_DICTIONARY.md §7 `indicator_values`) :
 * pour chaque indicateur intégré actif doté d'une clé de calcul, la valeur de la période (par défaut le mois
 * précédent) est calculée au niveau national (`zone_id` null) et, quand le calcul s'y prête, par département actif.
 *
 * Idempotent : `IndicatorValue::record()` met à jour la ligne existante (indicateur × période × zone). Une valeur
 * saisie à la main (`is_manual`) sur la même période n'est jamais écrasée. Un calcul sans donnée (dénominateur nul)
 * n'enregistre rien ; un indicateur dont la clé n'est pas calculable est signalé avec sa raison.
 */
final class SnapshotIndicators
{
    public function __construct(private readonly IndicatorCalculator $calculator) {}

    public function run(?Period $period = null): SnapshotResult
    {
        $period ??= Period::previousMonth();
        $result = new SnapshotResult($period);

        /** @var Collection<int, Indicator> $indicators */
        $indicators = Indicator::query()->builtin()->active()->computed()->ordered()->get();

        /** @var Collection<int, Zone>|null $zones */
        $zones = null;

        foreach ($indicators as $indicator) {
            $key = (string) $indicator->calc_key;

            if (! $this->calculator->supports($key)) {
                $result->unsupported[$indicator->code] = $this->calculator->unavailableReason($key);

                continue;
            }

            $result->indicators++;
            $this->snapshot($indicator, $key, $period, null, $result);

            if (! $this->calculator->isZonal($key)) {
                continue;
            }

            $zones ??= Zone::query()->active()->ordered()->get();
            $result->zones = $zones->count();

            foreach ($zones as $zone) {
                $this->snapshot($indicator, $key, $period, $zone, $result);
            }
        }

        Log::info('Pilotage : instantané des indicateurs calculé.', [
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'indicators' => $result->indicators,
            'zones' => $result->zones,
            'recorded' => $result->recorded,
            'without_data' => $result->withoutData,
            'manual_kept' => $result->manualKept,
            'unsupported' => array_keys($result->unsupported),
        ]);

        return $result;
    }

    private function snapshot(Indicator $indicator, string $key, Period $period, ?Zone $zone, SnapshotResult $result): void
    {
        $manual = IndicatorValue::query()
            ->where('indicator_id', $indicator->getKey())
            ->where('zone_id', $zone?->getKey())
            ->forPeriod($period->start, $period->end)
            ->manual()
            ->exists();

        if ($manual) {
            $result->manualKept++;

            return;
        }

        $computation = $this->calculator->compute($key, $period, $zone);

        if (! $computation->isAvailable()) {
            $result->withoutData++;

            if ($zone === null) {
                $result->withoutDataReasons[$indicator->code] = (string) $computation->reason;
            }

            return;
        }

        IndicatorValue::record($indicator, $period->start, $period->end, $zone, (float) $computation->value, false, $computation->base);
        $result->recorded++;
    }
}
