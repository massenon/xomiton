<?php

declare(strict_types=1);

namespace App\Domain\Newsletter;

/**
 * État affiché par les pages publiques de confirmation et de désinscription (prototype newsletter.php : `confirmed`,
 * `ask`, `unsubscribed`, `invalid`) ; le prototype confirmait dès le GET : ici le GET n'affiche que le bouton
 * (`Confirm`), le POST confirme (aperçus de liens des messageries et antivirus).
 */
enum SubscriptionPageState: string
{
    case Confirm = 'confirm';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Unsubscribe = 'unsubscribe';
    case Unsubscribed = 'unsubscribed';
    case Invalid = 'invalid';

    /** Statut HTTP de la page : lien inconnu 404, lien de confirmation expiré 410, sinon 200. */
    public function httpStatus(): int
    {
        return match ($this) {
            self::Invalid => 404,
            self::Expired => 410,
            default => 200,
        };
    }
}
