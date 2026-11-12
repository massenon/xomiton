<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\OutboundMessage;
use App\Application\Gateways\PublishResult;
use App\Enums\SocialNetwork;
use Illuminate\Support\Facades\Log;

/**
 * Pilote `log` (défaut, P14-04) : n'appelle aucun réseau et n'utilise pas le jeton. Consigne seulement le réseau, la
 * longueur du message et le nombre d'images (jamais le jeton ni le texte), puis renvoie « à publier à la main » : la remise reste en attente
 * jusqu'à ce qu'une personne publie le texte prêt à copier et la marque publiée (statut `manuel`). Une publication n'est
 * donc jamais déclarée en ligne sans l'être.
 */
final class LogGatewayTransport implements GatewayTransport
{
    public function post(SocialNetwork $network, string $token, string $target, OutboundMessage $message): PublishResult
    {
        Log::info('Passerelle (pilote log) : publication non envoyée au réseau.', [
            'network' => $network->value,
            'length' => mb_strlen($message->fullText()),
            'media' => count($message->media),
        ]);

        return PublishResult::awaitingManual($message->fullText());
    }
}
