<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Dispatch;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Rejeu des notifications d'alerte (`notifications:retry`, P5-03) : délais croissants (le dernier se répète),
 * plafond de tentatives, seuil du SMS de repli. Pur, configuré par App\Application\Alerts\Dispatch\RetryPolicyFactory.
 */
final class RetryPolicy
{
    /**
     * @param  non-empty-list<positive-int>  $backoffMinutes  délai avant la n-ième reprise
     */
    public function __construct(
        private readonly array $backoffMinutes,
        private readonly int $maxAttempts,
    ) {
        if ($backoffMinutes === [] || $maxAttempts < 1) {
            throw new InvalidArgumentException('RetryPolicy : délais et plafond requis.');
        }
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /** Prochaine tentative après `$attemptsDone` tentatives (null : plafond atteint, plus de rejeu). */
    public function nextAttemptAt(int $attemptsDone, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if ($this->isExhausted($attemptsDone)) {
            return null;
        }

        $index = min(max(0, $attemptsDone - 1), count($this->backoffMinutes) - 1);

        return $now->modify(sprintf('+%d minutes', max(1, (int) $this->backoffMinutes[$index])));
    }

    public function isExhausted(int $attemptsDone): bool
    {
        return $attemptsDone >= $this->maxAttempts;
    }

    public function shouldFallbackToSms(int $pushFailures, int $threshold): bool
    {
        return $pushFailures >= max(1, $threshold);
    }
}
