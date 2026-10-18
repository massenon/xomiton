<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Canal `fcm` (application Flutter, jetons d'appareil) : remise par le FcmSender lié dans le conteneur
 * (`log` tant que le paquet FCM et le compte de service Firebase ne sont pas installés, ADR-25).
 */
final class FcmChannel extends PushChannel
{
    public function __construct(private readonly FcmSender $sender) {}

    protected function kind(): string
    {
        return PushSubscription::KIND_FCM;
    }

    protected function deliver(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        return $this->sender->send($subscription, $message);
    }
}
