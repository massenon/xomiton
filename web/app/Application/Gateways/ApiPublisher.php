<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Application\Gateways\Transport\GatewayTransports;
use App\Enums\SocialNetwork;
use App\Models\OutboundPost;
use App\Models\SocialChannel;
use Throwable;

/**
 * Base des connecteurs à API (P14-04, ADR-15) : un connecteur par réseau, compte de l'organisation uniquement, jeton lu
 * chiffré en base et passé au transport sans jamais être journalisé. Aucune donnée de membre : le seul contenu transmis est
 * le message de la publication. Un refus ou une panne ne lève pas d'exception : PublishResult::failed() avec un message
 * court (code, jamais de jeton ni de réponse brute), rejoué par `gateways:publish`.
 */
abstract class ApiPublisher implements SocialPublisher
{
    abstract protected function network(): SocialNetwork;

    public function supports(SocialChannel $channel): bool
    {
        return $channel->network === $this->network();
    }

    public function publish(OutboundPost $post, SocialChannel $channel): PublishResult
    {
        $token = (string) $channel->access_token;
        $target = trim((string) ($channel->external_id ?? $channel->account_handle));
        $text = ManualPublisher::copyText($post);

        if ($token === '' || $target === '') {
            return PublishResult::failed('credentials_missing');
        }

        if ($text === '' || mb_strlen($text) > OutboundPost::MESSAGE_MAX_LENGTH) {
            return PublishResult::failed('message_invalid');
        }

        try {
            return GatewayTransports::make()->post($this->network(), $token, $target, $text);
        } catch (Throwable) {
            return PublishResult::failed('transport_error');
        }
    }
}
