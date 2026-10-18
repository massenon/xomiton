<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Notifications\PushMessage;
use App\Models\AlertNotification;
use App\Models\PushSubscription;

/**
 * Une remise externe à tenter (push vers un abonnement, SMS vers un numéro résolu à l'instant), rattachée à sa ligne
 * `alert_notifications`. Le numéro n'existe qu'en mémoire (ADR-33).
 */
final readonly class PlannedDelivery
{
    public const WEBPUSH = 'webpush';

    public const FCM = 'fcm';

    public const SMS = 'sms';

    private function __construct(
        public AlertNotification $row,
        public string $provider,
        public ?PushSubscription $subscription = null,
        public ?PushMessage $message = null,
        public ?string $to = null,
        public ?string $text = null,
    ) {}

    public static function push(AlertNotification $row, PushSubscription $subscription, PushMessage $message): self
    {
        return new self($row, $subscription->isFcm() ? self::FCM : self::WEBPUSH, $subscription, $message);
    }

    public static function sms(AlertNotification $row, string $to, string $text): self
    {
        return new self($row, self::SMS, to: $to, text: $text);
    }

    public function isPush(): bool
    {
        return $this->subscription !== null;
    }
}
