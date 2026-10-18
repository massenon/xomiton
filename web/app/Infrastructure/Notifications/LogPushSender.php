<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Pilote Web Push par défaut (WEBPUSH_DRIVER=log) : consigne la remise dans le journal au lieu de l'envoyer.
 * Reste en place tant que `minishlink/web-push` n'est pas installé (ADR-25). Seules les métadonnées sont journalisées
 * (empreinte de l'abonnement, type, catégorie, longueurs) : jamais l'endpoint, les clés, le titre ni le corps (aperçu
 * d'un message privé). En production, la remise est un échec (avertissement) pour déclencher replis et rejeu.
 */
final class LogPushSender implements PushSender
{
    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        return self::consign('webpush', $subscription, $message);
    }

    /**
     * Journalise une remise push factice (Web Push ou FCM) sans contenu ; échec en production.
     */
    public static function consign(string $channel, PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        $context = [
            'subscription' => $subscription->endpoint_hash,
            'user_id' => $subscription->user_id,
            'type' => $message->data['type'],
            'category' => $message->data['category'],
            'urgent' => $message->isUrgent(),
            'title_length' => mb_strlen($message->title),
            'body_length' => mb_strlen($message->body),
        ];

        if (app()->isProduction()) {
            Log::warning(sprintf('[%s:log] Pilote log en production : notification non envoyée.', $channel), $context);

            return DeliveryResult::failed($channel.': log driver, not delivered');
        }

        Log::info(sprintf('[%s:log] Notification push consignée (aucun envoi).', $channel), $context);

        return DeliveryResult::sent();
    }
}
