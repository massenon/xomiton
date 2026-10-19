<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Domain\Platform\Exceptions\MaintenanceAlreadyActive;
use App\Domain\Platform\Exceptions\MaintenanceLockout;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Active le mode maintenance (RG-14) : lève la bascule `maintenance`, pose le message personnalisé (`maint_msg`) et,
 * si une reprise est annoncée, ouvre une fenêtre `maintenance_windows` qui porte les dates affichées sur la page 503.
 * Journalisé dans audit_logs (action d'administration, DEVELOPMENT_STANDARDS.md §2) avec le motif interne.
 *
 * P13-06 : la reprise annoncée est une **estimation** affichée au public, pas une fin automatique — la bascule reste
 * levée jusqu'à DisableMaintenance (une mise à jour plus longue que prévu ne rouvre jamais la plateforme en cours de
 * route) ; seules les fenêtres planifiées (ScheduleMaintenance) se ferment seules. Garde-fou anti-verrouillage : un
 * compte qui ne garderait pas l'accès (MaintenanceStatus::isExempt) ne peut pas l'activer (MaintenanceLockout).
 * Idempotence : une maintenance déjà active (bascule ou fenêtre en cours) n'est jamais réactivée (MaintenanceAlreadyActive,
 * 422) — `maint_start`, l'avancement affiché et l'historique restent ceux de la première activation.
 * `$by` nul : commande de secours `xomiton:maintenance on` (journal « système »).
 */
final class EnableMaintenance
{
    /** Longueur du message public (MaintenanceStatus::MESSAGE_MAX_LENGTH, `maintenance_windows.message` string(280)). */
    public const MESSAGE_MAX_LENGTH = MaintenanceStatus::MESSAGE_MAX_LENGTH;

    /** Longueur du motif interne (`maintenance_windows.reason` string(255)). */
    public const REASON_MAX_LENGTH = 255;

    public function __invoke(?User $by, ?string $message = null, ?Carbon $endsAt = null, ?string $reason = null): ?MaintenanceWindow
    {
        if ($by !== null && ! MaintenanceStatus::isExempt($by)) {
            throw new MaintenanceLockout;
        }

        if (MaintenanceStatus::active()) {
            throw new MaintenanceAlreadyActive;
        }

        // Le Form Request / l'écran valident la longueur, le cas d'usage la garantit.
        $message = self::clean($message, self::MESSAGE_MAX_LENGTH);
        $reason = self::clean($reason, self::REASON_MAX_LENGTH);
        $startsAt = now();
        // Une reprise déjà passée n'est pas une reprise : elle n'ouvre pas de fenêtre et n'est pas annoncée.
        $endsAt = $endsAt !== null && $endsAt->gt($startsAt) ? $endsAt->copy() : null;
        $byId = $by === null ? null : (int) $by->getKey();

        $window = DB::transaction(function () use ($by, $byId, $message, $reason, $startsAt, $endsAt): ?MaintenanceWindow {
            Settings::set(MaintenanceStatus::SETTING_FLAG, true, $byId);
            Settings::set(MaintenanceStatus::SETTING_START, $startsAt->toIso8601String(), $byId);
            Settings::set(MaintenanceStatus::SETTING_END, $endsAt?->toIso8601String(), $byId);

            if ($message !== null) {
                Settings::set(MaintenanceStatus::SETTING_MESSAGE, $message, $byId);
            }

            $window = null;

            if ($endsAt !== null) {
                $window = MaintenanceWindow::query()->create([
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'message' => $message,
                    'reason' => $reason,
                    'announce_hours' => 0,
                    'is_active' => true,
                    'created_by' => $byId,
                ]);
            }

            MaintenanceAudit::log('maintenance.enabled', [
                'reason' => $reason,
                'message' => $message,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt?->toIso8601String(),
            ], $window, $by);

            return $window;
        });

        MaintenanceStatus::forget();

        return $window;
    }

    /** Texte nettoyé et borné, null s'il est vide. */
    public static function clean(?string $text, int $max): ?string
    {
        $text = $text === null ? '' : trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
