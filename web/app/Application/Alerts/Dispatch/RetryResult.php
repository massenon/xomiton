<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Models\AlertNotification;

/**
 * Bilan d'une exécution de `notifications:retry` : compteurs, lignes abandonnées ou à rejouer (sortie de la commande,
 * sans numéro ni nom : `#id`, canal, raison) et erreurs imprévues.
 */
final class RetryResult
{
    public int $examined = 0;

    public int $sent = 0;

    public int $failed = 0;

    public int $skipped = 0;

    public int $deferred = 0;

    public int $smsFallbacks = 0;

    /** @var list<array{id: int, channel: string, action: string, reason: string|null}> */
    public array $lines = [];

    /** @var list<array{id: int, error: string}> */
    public array $errors = [];

    public function __construct(public readonly bool $dryRun) {}

    public function count(AlertNotification $row, string $outcome): void
    {
        match ($outcome) {
            DeliveryRecorder::SENT => $this->sent++,
            DeliveryRecorder::FAILED => $this->failed++,
            DeliveryRecorder::DEFERRED => $this->deferred++,
            default => $this->skipped++,
        };

        if ($outcome === DeliveryRecorder::EXHAUSTED) {
            $this->lines[] = ['id' => (int) $row->getKey(), 'channel' => $row->channel->value, 'action' => 'skip', 'reason' => AlertNotification::SKIP_EXHAUSTED];
        }
    }

    public function skip(AlertNotification $row, string $reason): void
    {
        $this->skipped++;
        $this->lines[] = ['id' => (int) $row->getKey(), 'channel' => $row->channel->value, 'action' => 'skip', 'reason' => $reason];
    }

    public function retry(AlertNotification $row): void
    {
        $this->lines[] = ['id' => (int) $row->getKey(), 'channel' => $row->channel->value, 'action' => 'retry', 'reason' => null];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
