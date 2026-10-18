<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Canal `webpush` (navigateur, abonnements VAPID) : remise par le PushSender lié dans le conteneur
 * (`log` tant que minishlink/web-push n'est pas installé, ADR-25).
 */
final class WebPushChannel extends PushChannel
{
    public function __construct(private readonly PushSender $sender) {}

    protected function kind(): string
    {
        return PushSubscription::KIND_WEBPUSH;
    }

    protected function deliver(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        return $this->sender->send($subscription, $message);
    }
}
