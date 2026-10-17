<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Active le mode maintenance (RG-14) : lève la bascule `maintenance`, pose le message personnalisé (`maint_msg`) et,
 * si une reprise est annoncée, ouvre une fenêtre `maintenance_windows` qui porte les dates affichées sur la page 503.
 * Journalisé dans audit_logs (action d'administration, DEVELOPMENT_STANDARDS.md §2).
 */
final class EnableMaintenance
{
    /** Longueur de `maintenance_windows.message` (string(255)). */
    public const MESSAGE_MAX_LENGTH = 255;

    public function __invoke(User $by, ?string $message = null, ?Carbon $endsAt = null): ?MaintenanceWindow
    {
        // `maintenance_windows.message` est un string(255) (DATABASE_DICTIONARY.md §10) : le Form Request de
        // l'administration valide la longueur, le cas d'usage la garantit.
        $message = $message === null || trim($message) === '' ? null : mb_substr(trim($message), 0, self::MESSAGE_MAX_LENGTH);
        $startsAt = now();
        // Une reprise déjà passée n'est pas une reprise : elle n'ouvre pas de fenêtre et n'est pas annoncée.
        $endsAt = $endsAt !== null && $endsAt->gt($startsAt) ? $endsAt->copy() : null;

        return DB::transaction(function () use ($by, $message, $startsAt, $endsAt): ?MaintenanceWindow {
            Settings::set(MaintenanceStatus::SETTING_FLAG, true, (int) $by->getKey());
            Settings::set(MaintenanceStatus::SETTING_START, $startsAt->toIso8601String(), (int) $by->getKey());
            Settings::set(MaintenanceStatus::SETTING_END, $endsAt?->toIso8601String(), (int) $by->getKey());

            if ($message !== null) {
                Settings::set(MaintenanceStatus::SETTING_MESSAGE, $message, (int) $by->getKey());
            }

            $window = null;

            if ($endsAt !== null) {
                $window = MaintenanceWindow::query()->create([
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'message' => $message,
                    'is_active' => true,
                    'created_by' => $by->getKey(),
                ]);
            }

            Audit::logWith('maintenance.enabled', [
                'message' => $message,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt?->toIso8601String(),
            ], $window, $by);

            return $window;
        });
    }
}
