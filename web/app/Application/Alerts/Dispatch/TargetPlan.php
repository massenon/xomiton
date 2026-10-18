<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Enums\DeliveryStatus;
use App\Models\AlertNotification;
use App\Models\PushSubscription;
use App\Notifications\XomitonNotification;

/**
 * Lignes planifiées pour une personne lors d'un envoi (cloche, push par abonnement, SMS, e-mail).
 *
 * @internal
 */
final class TargetPlan
{
    public ?AlertNotification $database = null;

    /** @var list<array{0: AlertNotification, 1: PushSubscription}> */
    public array $push = [];

    public ?AlertNotification $sms = null;

    public ?AlertNotification $mail = null;

    public function __construct(
        public readonly RecipientTarget $target,
        public readonly XomitonNotification $notification,
    ) {}

    /**
     * @return list<AlertNotification>
     */
    public function rows(): array
    {
        return array_values(array_filter([
            $this->database,
            ...array_map(static fn (array $push): AlertNotification => $push[0], $this->push),
            $this->sms,
            $this->mail,
        ]));
    }

    /** Tous les push de la personne ont échoué ou visaient un abonnement disparu (repli SMS immédiat). */
    public function allPushFailed(): bool
    {
        if ($this->push === []) {
            return false;
        }

        foreach ($this->push as [$row]) {
            if (! in_array($row->status, [DeliveryStatus::Failed, DeliveryStatus::Skipped], true)) {
                return false;
            }
        }

        return true;
    }
}
