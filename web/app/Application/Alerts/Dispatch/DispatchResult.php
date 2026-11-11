<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Enums\NotificationPurpose;

/**
 * Compteurs d'un envoi (journal `alerts.dispatched`, audit `alerts.notified`) : aucune donnée personnelle.
 */
final class DispatchResult
{
    public int $recipients = 0;

    public int $planned = 0;

    public int $sent = 0;

    public int $failed = 0;

    public int $pending = 0;

    public int $skipped = 0;

    public int $smsFallbacks = 0;

    public function __construct(public readonly NotificationPurpose $purpose) {}

    public function count(string $outcome): void
    {
        match ($outcome) {
            DeliveryRecorder::SENT => $this->sent++,
            DeliveryRecorder::FAILED => $this->failed++,
            DeliveryRecorder::DEFERRED => $this->pending++,
            default => $this->skipped++,
        };
    }

    public function detail(int $elapsedMs): string
    {
        return sprintf(
            'purpose=%s ; destinataires=%d ; sent=%d ; failed=%d ; pending=%d ; skipped=%d ; sms_fallback=%d ; ms=%d',
            $this->purpose->value,
            $this->recipients,
            $this->sent,
            $this->failed,
            $this->pending,
            $this->skipped,
            $this->smsFallbacks,
            $elapsedMs,
        );
    }
}
