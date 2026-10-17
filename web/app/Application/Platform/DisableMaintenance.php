<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Lève le mode maintenance (RG-14) : baisse la bascule `maintenance`, efface les dates annoncées et clôt les fenêtres
 * `maintenance_windows` en cours (désactivées, jamais supprimées : elles restent dans l'historique).
 * Journalisé dans audit_logs.
 */
final class DisableMaintenance
{
    /**
     * @return int Nombre de fenêtres en cours clôturées.
     */
    public function __invoke(User $by): int
    {
        return DB::transaction(function () use ($by): int {
            Settings::set(MaintenanceStatus::SETTING_FLAG, false, (int) $by->getKey());
            Settings::set(MaintenanceStatus::SETTING_START, null, (int) $by->getKey());
            Settings::set(MaintenanceStatus::SETTING_END, null, (int) $by->getKey());

            $closed = 0;

            MaintenanceWindow::query()->ongoing()->each(function (MaintenanceWindow $window) use (&$closed): void {
                $window->update(['is_active' => false, 'ends_at' => now()]);
                $closed++;
            });

            Audit::logWith('maintenance.disabled', ['closed_windows' => $closed], null, $by);

            return $closed;
        });
    }
}
