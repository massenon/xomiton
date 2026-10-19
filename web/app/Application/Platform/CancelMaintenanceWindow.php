<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Domain\Platform\Exceptions\MaintenanceWindowClosed;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Annule une fenêtre de maintenance (P13-06) : à venir, elle est désactivée et marquée `cancelled_at` ; en cours,
 * elle est close maintenant (fin ramenée à l'instant). Jamais supprimée (historique). La bascule manuelle n'est pas
 * touchée : DisableMaintenance lève tout. Fenêtre déjà terminée ou annulée : MaintenanceWindowClosed.
 * Journal `maintenance.window_cancelled`.
 */
final class CancelMaintenanceWindow
{
    public function __invoke(User $by, MaintenanceWindow $window, ?string $reason = null): MaintenanceWindow
    {
        $reason = EnableMaintenance::clean($reason, EnableMaintenance::REASON_MAX_LENGTH);

        DB::transaction(function () use ($by, $window, $reason): void {
            $window = MaintenanceWindow::query()->lockForUpdate()->findOrFail($window->getKey());

            if (! $window->is_active || $window->isPast()) {
                throw new MaintenanceWindowClosed;
            }

            $ongoing = $window->isOngoing();
            $window->update($ongoing
                ? ['is_active' => false, 'ends_at' => now()]
                : ['is_active' => false, 'cancelled_at' => now()]);

            Audit::logWith('maintenance.window_cancelled', [
                'reason' => $reason,
                'ongoing' => $ongoing,
                'starts_at' => $window->starts_at->toIso8601String(),
            ], $window, $by);
        });

        MaintenanceStatus::forget();

        return $window->refresh();
    }
}
