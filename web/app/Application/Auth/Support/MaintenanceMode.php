<?php

declare(strict_types=1);

namespace App\Application\Auth\Support;

use App\Application\Platform\MaintenanceStatus;

/**
 * Maintenance applicative (RG-14, P2-11) vue depuis la connexion : bascule manuelle `maintenance` des paramètres
 * ou fenêtre `maintenance_windows` active et en cours. Seuls admin et superadmin se connectent alors.
 */
final class MaintenanceMode
{
    private function __construct() {}

    /**
     * Délègue à App\Application\Platform\MaintenanceStatus (lot pairs-maintenance, P2-11) : une seule source de vérité
     * pour la connexion, le middleware EnsureNotInMaintenance et la page 503.
     */
    public static function isActive(): bool
    {
        return MaintenanceStatus::active();
    }
}
