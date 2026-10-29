<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Enums\ModerationStatus;
use App\Models\ModerationReport;
use Illuminate\Database\Eloquent\Builder;

/**
 * File des signalements de contenu : une ligne par contenu signalé en attente (le plus ancien signalement de chaque
 * cible), avec le nombre de signalements et de personnes distinctes. Partagée par l'écran Modération (Livewire) et par
 * l'API admin mobile (`admin/moderation/reports`).
 */
final class ReportQueue
{
    /**
     * @return Builder<ModerationReport>
     */
    public function pendingGroups(): Builder
    {
        $oldest = ModerationReport::query()->toBase()
            ->where('status', ModerationStatus::Pending->value)
            ->groupBy('subject_type', 'subject_id')
            ->selectRaw('min(id)');

        return ModerationReport::query()->whereIn('id', $oldest);
    }

    /**
     * @return Builder<ModerationReport>
     */
    public function forState(string $state): Builder
    {
        return match ($state) {
            'handled' => ModerationReport::query()->withStatus(ModerationStatus::Handled),
            'rejected' => ModerationReport::query()->withStatus(ModerationStatus::Rejected),
            'all' => ModerationReport::query(),
            default => $this->pendingGroups(),
        };
    }

    /**
     * Signalements en attente par contenu de la page : nombre de signalements et de personnes distinctes.
     *
     * @param  iterable<ModerationReport>  $rows
     * @return array<string, array{reports: int, people: int}>
     */
    public function siblings(iterable $rows): array
    {
        $rows = collect($rows);

        if ($rows->isEmpty()) {
            return [];
        }

        $found = ModerationReport::query()->pending()->where(function (Builder $where) use ($rows): void {
            foreach ($rows as $row) {
                $where->orWhere(fn (Builder $one) => $one->where('subject_type', $row->subject_type)->where('subject_id', $row->subject_id));
            }
        })->toBase()->groupBy('subject_type', 'subject_id')
            ->selectRaw('subject_type, subject_id, count(*) as reports, count(distinct reporter_user_id) as people')->get();

        $map = [];

        foreach ($found as $line) {
            $map[$line->subject_type.':'.$line->subject_id] = ['reports' => (int) $line->reports, 'people' => (int) $line->people];
        }

        return $map;
    }
}
