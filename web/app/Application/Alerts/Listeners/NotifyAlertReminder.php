<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RoutedAlertRecipients;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Enums\NotificationPurpose;
use Carbon\CarbonImmutable;

/**
 * AlertReminderDue (`alerts:escalate`, Q27) → rappel « Rappel : alerte à prendre en charge » au contact routé actuel et
 * aux admins de garde (P5-03) ; contexte cron : e-mail `alerte-routee` envoyé directement (contexte `alerte_rappel`).
 */
final class NotifyAlertReminder
{
    use DispatchesAlertNotifications;

    public function __construct(
        private readonly AlertDispatcher $dispatcher,
        private readonly RoutedAlertRecipients $recipients,
    ) {}

    public function handle(AlertReminderDue $event): void
    {
        $alert = $this->loadAlert($event->alertId);

        if ($alert === null) {
            return;
        }

        $now = CarbonImmutable::instance($event->occurredAt);

        $this->guarded($alert, fn () => $this->dispatcher->dispatch(
            $alert,
            NotificationPurpose::AlertReminder,
            $this->recipients->for($alert, $now),
            DispatchContext::console($now),
        ));
    }
}
