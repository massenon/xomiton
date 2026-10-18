<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\EscalationRecipients;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Enums\NotificationPurpose;
use Carbon\CarbonImmutable;

/**
 * AlertEscalated (`alerts:escalate`, RG-05, Q48) → SOS : « SOS sans réponse » à la cible (point focal national : cloche,
 * push, SMS toujours, e-mail direct ; lien signé d'accusé pour une fiche sans compte) ou aux admins de garde ;
 * signalement / besoin à 48 h : « alerte routée » à la nouvelle cible (objet `alert_escalated`, distinct de la
 * création : les admins déjà prévenus le sont à nouveau), sans SMS (P5-03). Contexte cron.
 */
final class NotifyAlertEscalated
{
    use DispatchesAlertNotifications;

    public function __construct(
        private readonly AlertDispatcher $dispatcher,
        private readonly EscalationRecipients $recipients,
    ) {}

    public function handle(AlertEscalated $event): void
    {
        $alert = $this->loadAlert($event->alertId);

        if ($alert === null) {
            return;
        }

        $now = CarbonImmutable::instance($event->occurredAt);

        $this->guarded($alert, fn () => $this->dispatcher->dispatch(
            $alert,
            $alert->isSos() ? NotificationPurpose::SosUnacknowledged : NotificationPurpose::AlertEscalated,
            $this->recipients->for($alert, $event->toContactId, $event->adminIds),
            DispatchContext::console($now),
            ['minutes' => $event->minutesSinceCreation],
        ));
    }
}
