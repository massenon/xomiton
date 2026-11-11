<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Dispatch;

use Closure;

/**
 * Délais des remises externes d'une requête ou d'une exécution du cron (D7, P5-03) : `providerTimeout` secondes au plus
 * par fournisseur (délai HTTP et délai mural d'un groupe séquentiel), `requestBudget` secondes au plus pour l'ensemble.
 * Pur : l'horloge (secondes, flottant) est injectée pour les tests unitaires, `microtime(true)` par défaut.
 */
final class DeliveryBudget
{
    /** Délai HTTP minimal, pour ne jamais passer 0 (sans délai) au client HTTP. */
    public const MIN_HTTP_TIMEOUT = 0.1;

    private readonly Closure $clock;

    private readonly float $startedAt;

    private function __construct(
        public readonly float $providerTimeout,
        public readonly float $requestBudget,
        ?callable $clock,
    ) {
        $this->clock = $clock === null ? static fn (): float => microtime(true) : Closure::fromCallable($clock);
        $this->startedAt = $this->now();
    }

    public static function start(float $providerTimeoutSeconds, float $requestBudgetSeconds, ?callable $clock = null): self
    {
        return new self(max(0.0, $providerTimeoutSeconds), max(0.0, $requestBudgetSeconds), $clock);
    }

    public function now(): float
    {
        return (float) ($this->clock)();
    }

    public function elapsed(): float
    {
        return max(0.0, $this->now() - $this->startedAt);
    }

    public function remaining(): float
    {
        return max(0.0, $this->requestBudget - $this->elapsed());
    }

    public function exhausted(): bool
    {
        return $this->remaining() <= 0.0;
    }

    /** Délai d'une requête HTTP : le délai fournisseur, borné par le budget restant, jamais sous 0,1 s. */
    public function httpTimeout(): float
    {
        return max(self::MIN_HTTP_TIMEOUT, min($this->providerTimeout, $this->remaining()));
    }

    /** Le groupe séquentiel d'un fournisseur, commencé à `$groupStartedAt`, peut-il encore tenter une remise ? */
    public function providerWindowOpen(float $groupStartedAt): bool
    {
        return $this->now() - $groupStartedAt < $this->providerTimeout;
    }
}
