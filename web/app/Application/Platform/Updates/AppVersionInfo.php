<?php

declare(strict_types=1);

namespace App\Application\Platform\Updates;

use App\Models\AppVersion;
use App\Support\Settings;

/**
 * Charge utile de `GET /api/v1/app/version` (P13-02, mise à jour minimale mobile) : version courante de la plateforme,
 * version minimale acceptée par l'application mobile (`app_min_version`, jamais au-dessus de la courante), notes de la
 * version courante, et — si le client annonce la sienne (`?version=` ou `X-App-Version`) — `update_available` (une version
 * plus récente existe) et `update_required` (la version du client est inférieure au minimum : mise à jour obligatoire).
 */
final class AppVersionInfo
{
    public const PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * @return array{current: string, minimum: string, released_at: string|null, kind: string|null, notes: string|null, client_version: string|null, update_available: bool|null, update_required: bool|null}
     */
    public static function for(?string $clientVersion): array
    {
        $current = ManageVersions::currentVersion();
        $minimum = Settings::string('app_min_version', '1.0.0');
        $minimum = preg_match(self::PATTERN, $minimum) === 1 && version_compare($minimum, $current, '<=') ? $minimum : $current;
        $row = AppVersion::current();
        $client = $clientVersion !== null && preg_match(self::PATTERN, $clientVersion) === 1 ? $clientVersion : null;

        return [
            'current' => $current,
            'minimum' => $minimum,
            'released_at' => $row?->deployed_at?->toIso8601String(),
            'kind' => $row?->kind,
            'notes' => $row?->notes,
            'client_version' => $client,
            'update_available' => $client === null ? null : version_compare($client, $current, '<'),
            'update_required' => $client === null ? null : version_compare($client, $minimum, '<'),
        ];
    }
}
