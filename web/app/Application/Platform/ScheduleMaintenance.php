<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Domain\Platform\Exceptions\MaintenanceLockout;
use App\Domain\Platform\Exceptions\MaintenanceWindowInvalid;
use App\Domain\Platform\Exceptions\MaintenanceWindowOverlaps;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Planifie une fenêtre de maintenance (P13-06, RG-14) : début et fin (la fenêtre s'ouvre et se ferme seule, lue par
 * MaintenanceStatus), message public, annonce préalable N heures avant le début (bandeaux P13-08), motif interne.
 * Refus : début passé, fin avant le début, durée au-delà de MAX_HOURS (MaintenanceWindowInvalid), chevauchement d'une
 * fenêtre active en cours ou à venir (MaintenanceWindowOverlaps, 422), compte qui perdrait l'accès (MaintenanceLockout).
 * Journal `maintenance.scheduled`.
 */
final class ScheduleMaintenance
{
    /** Durée maximale d'une fenêtre (heures). */
    public const MAX_HOURS = 72;

    /** Tolérance sur un début « maintenant » saisi à la minute près. */
    private const PAST_TOLERANCE_SECONDS = 120;

    public function __invoke(User $by, Carbon $startsAt, Carbon $endsAt, ?string $message, int $announceHours, ?string $reason): MaintenanceWindow
    {
        if (! MaintenanceStatus::isExempt($by)) {
            throw new MaintenanceLockout;
        }

        // Saisie dans le fuseau d'affichage (écran d'administration) : stockée en UTC (`app.timezone`), comme toute date.
        $storage = (string) config('app.timezone', 'UTC');
        $startsAt = $startsAt->copy()->setTimezone($storage)->startOfMinute();
        $endsAt = $endsAt->copy()->setTimezone($storage)->startOfMinute();

        if ($startsAt->lt(now()->subSeconds(self::PAST_TOLERANCE_SECONDS))) {
            throw new MaintenanceWindowInvalid('past');
        }

        if ($endsAt->lte($startsAt)) {
            throw new MaintenanceWindowInvalid('order');
        }

        if ($startsAt->diffInMinutes($endsAt) > self::MAX_HOURS * 60) {
            throw new MaintenanceWindowInvalid('too_long', ['hours' => self::MAX_HOURS]);
        }

        $message = EnableMaintenance::clean($message, MaintenanceStatus::MESSAGE_MAX_LENGTH);
        $reason = EnableMaintenance::clean($reason, EnableMaintenance::REASON_MAX_LENGTH);
        $announceHours = max(0, min(MaintenanceStatus::MAX_ANNOUNCE_HOURS, $announceHours));

        $window = DB::transaction(function () use ($by, $startsAt, $endsAt, $message, $reason, $announceHours): MaintenanceWindow {
            $overlap = MaintenanceWindow::query()->overlapping($startsAt, $endsAt)->orderBy('starts_at')->lockForUpdate()->first();

            if ($overlap !== null) {
                $format = MaintenancePage::formatter();

                throw new MaintenanceWindowOverlaps($format->dateTime($overlap->starts_at), $format->dateTime($overlap->ends_at));
            }

            $window = MaintenanceWindow::query()->create([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'message' => $message,
                'reason' => $reason,
                'announce_hours' => $announceHours,
                'is_active' => true,
                'created_by' => $by->getKey(),
            ]);

            Audit::logWith('maintenance.scheduled', [
                'reason' => $reason,
                'message' => $message,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'announce_hours' => $announceHours,
            ], $window, $by);

            return $window;
        });

        MaintenanceStatus::forget();

        return $window;
    }
}
