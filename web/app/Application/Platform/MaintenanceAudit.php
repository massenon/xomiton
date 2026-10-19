<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des actions de maintenance (P13-06) : l'auteur·rice de l'écran d'administration, ou « système » pour la
 * commande de secours `xomiton:maintenance` (aucun compte, aucune IP), détail JSON masqué comme Audit::logWith.
 */
final class MaintenanceAudit
{
    private function __construct() {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $action, array $context, ?Model $subject, ?User $by): AuditLog
    {
        if ($by !== null) {
            return Audit::logWith($action, $context, $subject, $by);
        }

        $json = json_encode(Audit::redact($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Audit::logWithoutRequest($action, $subject, $json === false ? null : $json, AuditLog::ACTOR_SYSTEM);
    }
}
