<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Alerts\Dispatch\RetryPolicy;
use App\Enums\NotificationPurpose;
use App\Models\AlertNotification;
use Carbon\CarbonImmutable;

/**
 * Consigne le résultat d'une remise dans sa ligne `alert_notifications` (dans la requête comme au rejeu) :
 * non tentée → reprise à la minute suivante ; acceptée → `sent` ; abonnement disparu → `skipped` ; échec → `failed`
 * avec la prochaine reprise (`RetryPolicy`), aucune pour un objet non rejouable, abandon au plafond.
 */
final class DeliveryRecorder
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const DEFERRED = 'deferred';

    public const SKIPPED = 'skipped';

    public const EXHAUSTED = 'exhausted';

    public function __construct(private readonly RetryPolicy $policy) {}

    public static function fromConfig(): self
    {
        return new self(RetryPolicyFactory::fromConfig());
    }

    public function record(DeliveryOutcome $outcome, NotificationPurpose $purpose, CarbonImmutable $now): string
    {
        $row = $outcome->delivery->row;

        if (! $outcome->attempted) {
            $row->markDeferred($now);

            return self::DEFERRED;
        }

        if ($outcome->ok()) {
            $row->markSent($outcome->provider, $now);

            return self::SENT;
        }

        if ($outcome->gone()) {
            $row->markSkipped(AlertNotification::SKIP_GONE, $outcome->provider);

            return self::SKIPPED;
        }

        return $this->failed($row, $outcome->error(), $outcome->provider, $purpose, $now);
    }

    /** Échec d'une tentative réelle (pilote, cloche, e-mail). */
    public function failed(AlertNotification $row, string $error, ?string $provider, NotificationPurpose $purpose, CarbonImmutable $now): string
    {
        $attempts = $row->attempts + 1;

        if (! $purpose->isRetryable()) {
            $row->markFailed($error, $provider, null);

            return self::FAILED;
        }

        $next = $this->policy->nextAttemptAt($attempts, $now);

        if ($next === null) {
            $row->markFailed($error, $provider, null);
            $row->markSkipped(AlertNotification::SKIP_EXHAUSTED);

            return self::EXHAUSTED;
        }

        $row->markFailed($error, $provider, CarbonImmutable::instance($next));

        return self::FAILED;
    }
}
