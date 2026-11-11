<?php

declare(strict_types=1);

namespace App\Application\Maintenance;

/**
 * Bilan d'une exécution de `mail:flush-outbox`.
 */
final readonly class OutboxFlushResult
{
    public function __construct(
        public string $mailer,
        public bool $transportAvailable,
        public int $sent = 0,
        public int $failed = 0,
        public int $remaining = 0,
    ) {}

    public static function unavailable(string $mailer, int $remaining): self
    {
        return new self($mailer, false, 0, 0, $remaining);
    }

    public function processed(): int
    {
        return $this->sent + $this->failed;
    }
}
