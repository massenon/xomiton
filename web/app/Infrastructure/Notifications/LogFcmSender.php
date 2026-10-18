<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Pilote FCM par défaut (FCM_DRIVER=log) : consigne la charge utile dans le journal au lieu de l'envoyer à Firebase.
 * Reste en place tant que le paquet FCM et le compte de service (FIREBASE_CREDENTIALS) ne sont pas installés (ADR-25).
 */
final class LogFcmSender implements FcmSender
{
    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        Log::info('[fcm:log] Notification FCM consignée (aucun envoi).', [
            'subscription' => $subscription->endpoint_hash,
            'user_id' => $subscription->user_id,
            'urgent' => $message->isUrgent(),
            'payload' => $message->toArray(),
        ]);

        return DeliveryResult::sent();
    }
}
