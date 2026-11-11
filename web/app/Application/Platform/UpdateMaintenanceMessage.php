<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Message public de la page de maintenance (P13-06, éditeur 280 caractères) : paramètre `maint_msg` et message des
 * fenêtres en cours (affiché en priorité par MaintenanceStatus::message), pour que la page publique change tout de
 * suite pendant une maintenance. Vide : texte par défaut (`maintenance.default_message`). Journal
 * `maintenance.message_updated`.
 */
final class UpdateMaintenanceMessage
{
    public function __invoke(User $by, ?string $message): ?string
    {
        $message = EnableMaintenance::clean($message, MaintenanceStatus::MESSAGE_MAX_LENGTH);

        DB::transaction(function () use ($by, $message): void {
            Settings::set(MaintenanceStatus::SETTING_MESSAGE, $message ?? '', (int) $by->getKey());

            MaintenanceWindow::query()->ongoing()->update(['message' => $message]);

            Audit::logWith('maintenance.message_updated', ['message' => $message], null, $by);
        });

        MaintenanceStatus::forget();

        return $message;
    }
}
