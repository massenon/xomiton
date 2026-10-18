<?php

declare(strict_types=1);

namespace App\Application\Offline;

use App\Support\KeyedHash;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Compte d'un envoi de la boîte d'envoi (P5-10, ADR-26 ; resources/js/shared/outbox.js) : la page qui met en file
 * transmet l'empreinte de son compte (`data-account` de partials/offline, vide sans compte), gardée avec l'entrée et
 * renvoyée au rejeu dans X-Xm-Outbox-Account (`guest` sans compte). Le serveur (BindOutboxAccount) traite l'envoi comme
 * anonyme quand la session du rejeu n'est pas ce compte : un SOS ou un signalement mis en file sans compte n'est jamais
 * rattaché au compte connecté plus tard sur l'appareil. Empreinte HMAC à clé d'application : aucun identifiant en clair.
 */
final class OutboxAccount
{
    public const HEADER = 'X-Xm-Outbox-Account';

    public const GUEST = 'guest';

    private const LENGTH = 32;

    private function __construct() {}

    /** Empreinte du compte, '' sans compte. */
    public static function fingerprint(?Authenticatable $user): string
    {
        return $user === null ? '' : KeyedHash::make('outbox-account|'.$user->getAuthIdentifier(), self::LENGTH);
    }

    /** L'envoi annonce un autre compte que la session (ou aucun) : il part sans compte. */
    public static function mismatch(?string $announced, ?Authenticatable $user): bool
    {
        if ($announced === null || $announced === '' || $user === null) {
            return false;
        }

        return $announced === self::GUEST || ! hash_equals(self::fingerprint($user), $announced);
    }
}
