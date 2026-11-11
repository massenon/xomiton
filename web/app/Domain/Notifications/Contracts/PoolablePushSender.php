<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Capacité optionnelle d'un PushSender / FcmSender HTTP (P5-03) : décrire la requête pour qu'elle parte dans le
 * `Http::pool` du DeliveryRunner, puis interpréter la réponse. Un pilote qui ne l'implémente pas est appelé par
 * `send()`, en séquence, sous le même délai mural par fournisseur.
 */
interface PoolablePushSender
{
    /** null = cet abonnement ne peut pas être poolé : le runner retombe sur send(). */
    public function describeRequest(PushSubscription $subscription, PushMessage $message): ?HttpRequestSpec;

    /** 2xx → sent ; 404 / 410 (FCM UNREGISTERED) → gone ; autre → failed. */
    public function interpretResponse(int $status, string $body): DeliveryResult;

    /** Délai dépassé, DNS, TLS : toujours failed (rejoué par notifications:retry). */
    public function interpretTransportError(string $error): DeliveryResult;
}
