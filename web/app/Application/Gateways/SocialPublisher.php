<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Models\OutboundPost;
use App\Models\SocialChannel;

/**
 * Connecteur d'un réseau social (ADR-15, D4-bis) : publie une publication sortante sur le compte **de l'organisation**
 * relié à un SocialChannel. Aucune donnée de membre ne transite jamais par un connecteur. Les connecteurs réels
 * (FacebookPublisher, TelegramPublisher, TikTokPublisher) arrivent avec P14-04 ; seul ManualPublisher est livré avec
 * l'écran d'administration (P12-08). Un connecteur ne lève pas d'exception pour un refus du réseau : il renvoie
 * PublishResult::failed() (sans jeton ni réponse brute dans le message).
 */
interface SocialPublisher
{
    /** Ce connecteur prend-il en charge ce réseau ? */
    public function supports(SocialChannel $channel): bool;

    public function publish(OutboundPost $post, SocialChannel $channel): PublishResult;
}
