<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\PublishResult;
use App\Enums\SocialNetwork;

/**
 * Point d'extension réseau des connecteurs (P14-04, ADR-15) : c'est la **seule** couche qui parle à un réseau.
 * `FacebookPublisher` et `TelegramPublisher` préparent le message, le contrôlent puis le confient à un transport choisi
 * par `config('xomiton.gateways.driver')` (GatewayTransports). Livré : `log` (aucun appel réseau, aucune clé). Pour brancher
 * l'API réelle (Graph API, Bot API) : écrire une classe qui implémente cette interface (client HTTP, délai court, jamais de
 * jeton ni de réponse brute dans le message d'erreur renvoyé), l'ajouter à GatewayTransports::DRIVERS sous un nom, puis
 * régler `XOMITON_GATEWAYS_DRIVER`. Les connecteurs n'ont pas à changer.
 */
interface GatewayTransport
{
    /**
     * @param  string  $token  jeton du compte de l'organisation (déchiffré), jamais journalisé
     * @param  string  $target  identifiant de la page ou du canal (`external_id`, sinon l'identifiant public du compte)
     */
    public function post(SocialNetwork $network, string $token, string $target, string $text): PublishResult;
}
