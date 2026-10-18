<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Pilote FCM par défaut (FCM_DRIVER=log) : consigne la remise dans le journal au lieu de l'envoyer à Firebase.
 * Reste en place tant que le paquet FCM et le compte de service (FIREBASE_CREDENTIALS) ne sont pas installés (ADR-25).
 * Mêmes règles que LogPushSender : métadonnées seulement, échec en production.
 */
final class LogFcmSender implements FcmSender
{
    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        return LogPushSender::consign('fcm', $subscription, $message);
    }
}
