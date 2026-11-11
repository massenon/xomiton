<?php

declare(strict_types=1);

namespace App\Application\Alerts\Support;

use App\Models\Alert;
use App\Support\KeyedHash;

/**
 * Preuve d'identité de la personne à l'origine d'un SOS anonyme (Q52 b) : jeton `X-Sos-Token` exigé pour l'annuler ou
 * dire « Je suis en sécurité ». Connaître l'ULID ne suffit plus : il figure dans le lien d'accusé envoyé aux contacts,
 * dans les journaux et dans les réponses de l'API. Le jeton est l'empreinte HMAC (APP_KEY, KeyedHash) de la clé
 * d'idempotence, secret aléatoire tenu par le seul appareil d'envoi et jamais rendu par l'API : rien de plus n'est
 * stocké, et un rejeu ADR-32 redonne le même jeton. Un SOS sans clé (import) n'en a pas.
 */
final class SosOwnerToken
{
    public const HEADER = 'X-Sos-Token';

    private function __construct() {}

    /** Jeton d'un SOS anonyme, null pour un SOS rattaché à un compte ou sans clé d'idempotence. */
    public static function for(Alert $alert): ?string
    {
        $key = $alert->idempotency_key;

        if (! $alert->isSos() || $alert->user_id !== null || ! is_string($key) || $key === '') {
            return null;
        }

        return KeyedHash::make('alerts.sos-owner|'.$key);
    }

    public static function matches(Alert $alert, ?string $token): bool
    {
        $expected = self::for($alert);

        return $expected !== null && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }
}
