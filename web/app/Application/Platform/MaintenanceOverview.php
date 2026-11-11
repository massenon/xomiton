<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Enums\MaintenanceState;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Lecture de l'écran Mode maintenance (P13-06) : état (inactif · annoncé · en cours · terminé), photographie publique,
 * fenêtre annoncée, fenêtres planifiées, dernière fin et historique (qui, quand, motif) depuis `audit_logs`.
 */
final readonly class MaintenanceOverview
{
    /** Actions du journal lues par l'historique de l'écran. */
    public const HISTORY_ACTIONS = [
        'maintenance.enabled', 'maintenance.disabled', 'maintenance.scheduled', 'maintenance.window_cancelled',
        'maintenance.message_updated',
    ];

    /** Durée pendant laquelle l'état « terminé » reste affiché après la fin d'une maintenance. */
    public const ENDED_FOR_HOURS = 24;

    /**
     * @param  Collection<int, MaintenanceWindow>  $upcoming
     * @param  Collection<int, AuditLog>  $history
     */
    public function __construct(
        public MaintenanceState $state,
        public MaintenanceSnapshot $snapshot,
        public ?MaintenanceWindow $announced,
        public Collection $upcoming,
        public ?Carbon $endedAt,
        public Collection $history,
    ) {}

    public static function make(int $historyLimit = 12): self
    {
        $now = now();
        $snapshot = MaintenanceStatus::snapshot($now);
        $announced = $snapshot->active ? null : MaintenanceStatus::announcement($now);
        $endedAt = self::lastEnd($now);

        $state = match (true) {
            $snapshot->active => MaintenanceState::Ongoing,
            $announced !== null => MaintenanceState::Announced,
            $endedAt !== null && $endedAt->gte($now->copy()->subHours(self::ENDED_FOR_HOURS)) => MaintenanceState::Ended,
            default => MaintenanceState::Inactive,
        };

        return new self(
            $state,
            $snapshot,
            $announced,
            MaintenanceWindow::query()->upcoming($now)->with('creator')->limit(20)->get(),
            $endedAt,
            AuditLog::query()->whereIn('action', self::HISTORY_ACTIONS)->with('user')->latestFirst()->limit($historyLimit)->get(),
        );
    }

    /** Dernière fin : levée de la bascule (journal) ou fenêtre terminée, la plus récente. */
    private static function lastEnd(Carbon $now): ?Carbon
    {
        $disabled = AuditLog::query()->where('action', 'maintenance.disabled')->latestFirst()->value('created_at');
        $window = MaintenanceWindow::query()->whereNull('cancelled_at')->where('ends_at', '<=', $now)->max('ends_at');
        $dates = array_filter([
            $disabled === null ? null : Carbon::parse($disabled),
            $window === null ? null : Carbon::parse($window),
        ]);

        if ($dates === []) {
            return null;
        }

        usort($dates, static fn (Carbon $a, Carbon $b): int => $b <=> $a);

        return $dates[0];
    }

    /**
     * Motif interne et message d'une entrée du journal (détail JSON écrit par les cas d'usage).
     *
     * @return array{reason: ?string, message: ?string, starts_at: ?string, ends_at: ?string}
     */
    public static function detailOf(AuditLog $log): array
    {
        $detail = json_decode((string) $log->detail, true);
        $detail = is_array($detail) ? $detail : [];
        $string = static fn (string $key): ?string => isset($detail[$key]) && is_string($detail[$key]) && trim($detail[$key]) !== '' ? $detail[$key] : null;

        return [
            'reason' => $string('reason'),
            'message' => $string('message'),
            'starts_at' => $string('starts_at'),
            'ends_at' => $string('ends_at'),
        ];
    }
}
