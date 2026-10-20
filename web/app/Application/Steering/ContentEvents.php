<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Enums\ContentEventKind;
use App\Models\ContentEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Compteurs d'événements de contenu (P10-06) : `record()` ajoute 1 au compteur du jour, sans rien d'autre (aucun
 * identifiant, aucune IP) ; `purge()` supprime les compteurs au-delà de la rétention (30 jours par défaut,
 * `xomiton.content_events.retention_days`).
 */
final class ContentEvents
{
    public function record(ContentEventKind $kind): void
    {
        $day = now()->toDateString();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $updated = ContentEvent::query()->where('kind', $kind->value)->whereDate('day', $day)
                ->update(['hits' => DB::raw('hits + 1'), 'updated_at' => now()]);

            if ($updated > 0) {
                return;
            }

            try {
                ContentEvent::query()->create(['kind' => $kind->value, 'day' => $day, 'hits' => 1, 'updated_at' => now()]);

                return;
            } catch (UniqueConstraintViolationException) {
                // Une requête concurrente a créé le compteur du jour : nouvelle tentative par mise à jour.
            }
        }
    }

    /**
     * Total d'un événement sur la période (bornes incluses).
     */
    public function total(ContentEventKind $kind, Period $period): int
    {
        return (int) ContentEvent::query()->where('kind', $kind->value)
            ->whereDate('day', '>=', $period->start->toDateString())
            ->whereDate('day', '<=', $period->end->toDateString())
            ->sum('hits');
    }

    public static function retentionDays(): int
    {
        return max(1, (int) config('xomiton.content_events.retention_days', 30));
    }

    /**
     * @return int compteurs supprimés
     */
    public function purge(?CarbonInterface $now = null): int
    {
        $limit = ($now ?? now())->copy()->subDays(self::retentionDays())->toDateString();

        return ContentEvent::query()->whereDate('day', '<', $limit)->delete();
    }
}
