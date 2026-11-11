<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Pilote de session `database` (D7, cPanel) sans adresse IP ni user agent pour une session d'invité·e : visiter
 * `/signaler`, `/sos` ou `/urgences` sans compte ne laisse aucune IP en base (RG-01, encadré d'anonymat de /signaler,
 * ADR-33). Une session connectée les garde, pour la liste des appareils connectés (ConnectedDevices, `me/sessions`) ;
 * la ligne d'un·e invité·e qui se connecte est régénérée et les reçoit alors. Enregistré par AppServiceProvider
 * (`Session::extend('database')`), le contenu de la session est par ailleurs chiffré (`session.encrypt`).
 */
final class GuestPrivateDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * @param  array<string, mixed>  $payload
     * @return $this
     */
    protected function addRequestInformation(&$payload): static
    {
        if (($payload['user_id'] ?? null) === null) {
            $payload['ip_address'] = null;
            $payload['user_agent'] = null;

            return $this;
        }

        parent::addRequestInformation($payload);

        return $this;
    }
}
