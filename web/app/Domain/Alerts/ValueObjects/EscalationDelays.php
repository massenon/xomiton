<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

use InvalidArgumentException;

/**
 * Délais RG-05 d'un type d'alerte, en minutes depuis la création (Q27) ; null = pas de rappel / pas d'escalade.
 */
final readonly class EscalationDelays
{
    public function __construct(
        public ?int $remindAfterMinutes = null,
        public ?int $escalateAfterMinutes = null,
    ) {
        foreach ([$remindAfterMinutes, $escalateAfterMinutes] as $minutes) {
            if ($minutes !== null && $minutes < 1) {
                throw new InvalidArgumentException('Escalation delays must be at least one minute.');
            }
        }
    }

    public static function none(): self
    {
        return new self;
    }

    /**
     * @param  array{remind_after_minutes?: int|string|null, escalate_after_minutes?: int|string|null}  $config
     */
    public static function fromArray(array $config): self
    {
        $read = static fn (mixed $value): ?int => is_numeric($value) && (int) $value > 0 ? (int) $value : null;

        return new self($read($config['remind_after_minutes'] ?? null), $read($config['escalate_after_minutes'] ?? null));
    }

    public function shortest(): ?int
    {
        $values = array_filter([$this->remindAfterMinutes, $this->escalateAfterMinutes], static fn (?int $v): bool => $v !== null);

        return $values === [] ? null : min($values);
    }
}
