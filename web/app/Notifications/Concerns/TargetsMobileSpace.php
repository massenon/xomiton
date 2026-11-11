<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

/**
 * Alertes adressées à l'équipe (SOS, alerte routée, SOS non accusé) : le **type** du push dit à l'application mobile
 * dans quel espace s'ouvre l'alerte (P15-19) et `id` porte l'ULID de l'alerte (le même identifiant que les routes
 * `pair/alerts/{ulid}` et `admin/alerts/{ulid}`) :
 *  - audience `pair` → `alert_routed` (ouvre `/pair/alertes/:id`) ;
 *  - audience `admin` → `duty_sos` pour un SOS, `duty_alert` pour une autre alerte (ouvre `/admin/alertes/:id`) ;
 *  - sans audience (contact de l'annuaire sans espace mobile, auteur·e) → type historique (`sos`, `alert`).
 * Pour un compte de l'équipe, le **mode discret** du profil vaut « aperçus masqués » : texte neutre, jamais « SOS »,
 * « VBG » ni « admin » (NOTIFICATIONS §4.1). La classe qui l'utilise déclare `$audience` et `$alertUlid`.
 *
 * @property-read string|null $audience
 * @property-read string|null $alertUlid
 */
trait TargetsMobileSpace
{
    use MasksInDiscreetMode;

    public const AUDIENCE_PAIR = 'pair';

    public const AUDIENCE_ADMIN = 'admin';

    /** Type de push selon l'audience ; `$adminType` : `duty_sos` ou `duty_alert` ; `$legacy` : type sans espace mobile. */
    protected function typeFor(string $adminType, string $legacy): string
    {
        return match ($this->audience) {
            self::AUDIENCE_ADMIN => $adminType,
            self::AUDIENCE_PAIR => 'alert_routed',
            default => $legacy,
        };
    }

    /** ULID de l'alerte pour un destinataire de l'équipe (ouverture profonde), sinon l'identifiant historique. */
    protected function idFor(int $legacy): int|string
    {
        return $this->audience !== null && $this->alertUlid !== null && $this->alertUlid !== '' ? $this->alertUlid : $legacy;
    }

    /** Mode discret = aperçus masqués pour un destinataire de l'équipe (audience connue) seulement. */
    protected function discreetModeMasks(): bool
    {
        return $this->audience !== null;
    }
}
