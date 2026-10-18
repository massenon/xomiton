<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;

/**
 * Capacité optionnelle d'un SmsSender HTTP (P5-03) : requête décrite pour le `Http::pool` du DeliveryRunner.
 */
interface PoolableSmsSender
{
    /** null = ce numéro ne peut pas être poolé : le runner retombe sur send(). */
    public function describeRequest(string $to, string $text): ?HttpRequestSpec;

    public function interpretResponse(int $status, string $body): DeliveryResult;

    public function interpretTransportError(string $error): DeliveryResult;
}
