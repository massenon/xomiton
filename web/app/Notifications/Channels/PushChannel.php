<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;
use App\Notifications\XomitonNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Socle des canaux push (`webpush`, `fcm`, ADR-25) : une remise par abonnement `push_subscriptions` du type du canal,
 * via le pilote du domaine (PushSender / FcmSender). Un abonnement « disparu » (404 / 410, jeton désinscrit) est
 * supprimé ; un échec rejouable est journalisé (rejeu : `notifications:retry`, P5) ; une exception du fournisseur ne
 * remonte jamais : la cloche et les autres canaux ont déjà été servis ou le seront.
 *
 * @return list<DeliveryResult>
 */
abstract class PushChannel
{
    /** Type d'abonnement servi (PushSubscription::KIND_*). */
    abstract protected function kind(): string;

    abstract protected function deliver(PushSubscription $subscription, PushMessage $message): DeliveryResult;

    /**
     * @param  mixed  $notifiable
     * @return list<DeliveryResult>
     */
    public function send($notifiable, Notification $notification): array
    {
        if (! $notification instanceof XomitonNotification || ! is_object($notifiable) || ! method_exists($notifiable, 'pushSubscriptions')) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, PushSubscription> $subscriptions */
        $subscriptions = $notifiable->pushSubscriptions()->ofKind($this->kind())->get();

        if ($subscriptions->isEmpty()) {
            return [];
        }

        $message = $notification->toPush($notifiable);
        $results = [];

        foreach ($subscriptions as $subscription) {
            $results[] = $this->attempt($subscription, $message, $notification);
        }

        return $results;
    }

    private function attempt(PushSubscription $subscription, PushMessage $message, XomitonNotification $notification): DeliveryResult
    {
        try {
            $result = $this->deliver($subscription, $message);
        } catch (Throwable $e) {
            $result = DeliveryResult::failed($e->getMessage());
        }

        if ($result->gone) {
            Log::info(sprintf('[%s] Abonnement push disparu : suppression.', $this->kind()), ['subscription' => $subscription->endpoint_hash, 'error' => $result->error]);
            $subscription->recordDelivery($result);

            return $result;
        }

        if (! $result->ok) {
            Log::warning(sprintf('[%s] Échec d’envoi push.', $this->kind()), [
                'subscription' => $subscription->endpoint_hash,
                'notification' => $notification::class,
                'error' => $result->error,
            ]);

            return $result;
        }

        $subscription->recordDelivery($result);

        return $result;
    }
}
