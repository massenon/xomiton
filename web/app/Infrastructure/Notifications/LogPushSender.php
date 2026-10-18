<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Pilote Web Push par défaut (WEBPUSH_DRIVER=log) : consigne la charge utile dans le journal au lieu de l'envoyer.
 * Reste en place tant que `minishlink/web-push` n'est pas installé (ADR-25) ; l'empreinte de l'abonnement est
 * journalisée, jamais l'endpoint ni les clés.
 */
final class LogPushSender implements PushSender
{
    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        Log::info('[webpush:log] Notification push consignée (aucun envoi).', [
            'subscription' => $subscription->endpoint_hash,
            'user_id' => $subscription->user_id,
            'payload' => $message->toArray(),
        ]);

        return DeliveryResult::sent();
    }
}
