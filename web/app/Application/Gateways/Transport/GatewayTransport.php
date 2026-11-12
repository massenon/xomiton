<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\OutboundMessage;
use App\Application\Gateways\PublishResult;
use App\Enums\SocialNetwork;

/**
 * Point d'extension réseau des connecteurs (P14-04, ADR-15) : c'est la **seule** couche qui parle à un réseau.
 * `FacebookPublisher` et `TelegramPublisher` préparent le message, le contrôlent puis le confient à un transport choisi
 * par `config('xomiton.gateways.driver')` (GatewayTransports). Livrés : `log` (défaut : aucun appel réseau, aucune clé) et
 * `http` (P19-11 : Graph API et Bot API réelles, HttpGatewayTransport). Un transport ne renvoie jamais de jeton ni de
 * réponse brute dans son message d'erreur (GatewayError).
 */
interface GatewayTransport
{
    /**
     * @param  string  $token  jeton du compte de l'organisation (déchiffré), jamais journalisé
     * @param  string  $target  identifiant de la page ou du canal (`external_id`, sinon l'identifiant public du compte)
     * @param  OutboundMessage  $message  message, lien public et images (OutboundContent)
     */
    public function post(SocialNetwork $network, string $token, string $target, OutboundMessage $message): PublishResult;
}
