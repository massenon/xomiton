<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\OutboundMessage;
use App\Application\Gateways\PublishResult;
use App\Enums\SocialNetwork;

/**
 * Pilote `http` (P19-11, `XOMITON_GATEWAYS_DRIVER=http`) : appels réels à la Graph API (page Facebook) et à la Bot API
 * (canal Telegram). Tout autre réseau reste à la publication manuelle assistée. Aucun journal applicatif ici : seul
 * l'audit de PublishDueDeliveries (sans texte ni jeton) garde la trace d'une publication.
 */
final class HttpGatewayTransport implements GatewayTransport
{
    public function __construct(
        private readonly FacebookGraphApi $facebook,
        private readonly TelegramBotApi $telegram,
    ) {}

    public function post(SocialNetwork $network, string $token, string $target, OutboundMessage $message): PublishResult
    {
        return match ($network) {
            SocialNetwork::Facebook => $this->facebook->publish($token, $target, $message),
            SocialNetwork::Telegram => $this->telegram->publish($token, $target, $message),
            default => PublishResult::awaitingManual($message->fullText()),
        };
    }
}
