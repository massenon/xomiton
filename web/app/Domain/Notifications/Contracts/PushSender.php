<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Envoi d'une notification Web Push (navigateur, abonnement VAPID `push_subscriptions.kind = webpush`), ADR-25.
 *
 * Implémentations : App\Infrastructure\Notifications\LogPushSender (journal, pilote par défaut tant que
 * `minishlink/web-push` n'est pas installé). Point d'extension : une classe `VapidPushSender` qui construit
 * `Minishlink\WebPush\WebPush` avec `config('xomiton.notifications.push.webpush.vapid')` et un
 * `Minishlink\WebPush\Subscription` depuis `endpoint`, `public_key` (p256dh) et `auth_token`, puis renvoie
 * `DeliveryResult::gone()` sur un 404 / 410 pour que le canal supprime l'abonnement (`notifications:retry`).
 * La charge utile (`PushMessage::toJson()`) ne contient jamais de donnée sensible (NOTIFICATIONS §4.1).
 * Un pilote HTTP implémente PoolablePushSender (envoi borné par DeliveryRunner, P5-03) ou applique lui-même
 * `xomiton.alerts.provider_timeout_seconds` à sa requête : un `send()` bloquant n'est pas interrompu (D13).
 */
interface PushSender
{
    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult;
}
