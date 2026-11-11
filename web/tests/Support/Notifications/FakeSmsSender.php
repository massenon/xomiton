<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use Closure;

/**
 * Faux pilote SMS des tests P5-03 : résultats programmables, lenteur simulée, journal des appels (numéro, texte).
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<DeliveryResult|\Throwable> */
    private array $results = [];

    private float $sleep = 0.0;

    private ?Closure $before = null;

    /** @var list<array{to: string, text: string}> */
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

    public function send(string $to, string $text): DeliveryResult
    {
        if ($this->before !== null) {
            ($this->before)();
        }

        $this->calls[] = ['to' => $to, 'text' => $text];

        if ($this->sleep > 0) {
            usleep((int) ($this->sleep * 1_000_000));
        }

        $result = count($this->results) > 1 ? array_shift($this->results) : ($this->results[0] ?? DeliveryResult::sent());

        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }
}
