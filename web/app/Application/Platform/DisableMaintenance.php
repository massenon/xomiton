<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Lève le mode maintenance (RG-14) : baisse la bascule `maintenance`, efface les dates annoncées et clôt les fenêtres
 * `maintenance_windows` en cours (désactivées, jamais supprimées : elles restent dans l'historique). Les fenêtres à
 * venir restent planifiées (CancelMaintenanceWindow les annule une à une). Journalisé dans audit_logs avec le motif.
 * `$by` nul : commande de secours `xomiton:maintenance off` (journal « système »).
 */
final class DisableMaintenance
{
    /**
     * @return int Nombre de fenêtres en cours clôturées.
     */
    public function __invoke(?User $by, ?string $reason = null): int
    {
        $reason = EnableMaintenance::clean($reason, EnableMaintenance::REASON_MAX_LENGTH);
        $byId = $by === null ? null : (int) $by->getKey();

        $closed = DB::transaction(function () use ($by, $byId, $reason): int {
            Settings::set(MaintenanceStatus::SETTING_FLAG, false, $byId);
            Settings::set(MaintenanceStatus::SETTING_START, null, $byId);
            Settings::set(MaintenanceStatus::SETTING_END, null, $byId);

            $closed = 0;

            MaintenanceWindow::query()->ongoing()->each(function (MaintenanceWindow $window) use (&$closed): void {
                $window->update(['is_active' => false, 'ends_at' => now()]);
                $closed++;
            });

            MaintenanceAudit::log('maintenance.disabled', ['reason' => $reason, 'closed_windows' => $closed], null, $by);

            return $closed;
        });

        MaintenanceStatus::forget();

        return $closed;
    }
}
