<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;
use Closure;

/**
 * Faux pilote push (Web Push et FCM) des tests P5-03 : résultats programmables (le dernier se répète, `sent` par
 * défaut), lenteur simulée, journal des appels (abonnement, message), crochet appelé avant chaque envoi.
 */
class FakePushSender implements FcmSender, PushSender
{
    /** @var list<DeliveryResult|\Throwable> */
    private array $results = [];

    private float $sleep = 0.0;

    private ?Closure $before = null;

    /** @var list<array{subscription: int, message: PushMessage}> */
    public array $calls = [];

    public function willReturn(DeliveryResult|\Throwable ...$results): static
    {
        $this->results = array_values($results);

        return $this;
    }

    public function sleepSeconds(float $seconds): static
    {
        $this->sleep = $seconds;

        return $this;
    }

    public function beforeSend(Closure $callback): static
    {
        $this->before = $callback;

        return $this;
    }

    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        if ($this->before !== null) {
            ($this->before)();
        }

        $this->calls[] = ['subscription' => (int) $subscription->getKey(), 'message' => $message];

        if ($this->sleep > 0) {
            usleep((int) ($this->sleep * 1_000_000));
        }

        return $this->next();
    }

    protected function next(): DeliveryResult
    {
        $result = count($this->results) > 1 ? array_shift($this->results) : ($this->results[0] ?? DeliveryResult::sent());

        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}
