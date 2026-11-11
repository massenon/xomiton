<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RecipientResolver;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\SosCancelled;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationPurpose;
use App\Models\AlertNotification;

/**
 * SosCancelled (repentir 60 s, P5-02) → plus aucune notification SOS ne part (lignes en attente abandonnées) et les
 * personnes déjà prévenues reçoivent « Fausse alerte » : cloche et push pour les comptes, un SMS au contact de
 * confiance et aux fiches sans compte (P5-03).
 */
final class NotifySosCancelled
{
    use DispatchesAlertNotifications;

    public function __construct(
        private readonly AlertDispatcher $dispatcher,
        private readonly RecipientResolver $resolver,
    ) {}

    public function handle(SosCancelled $event): void
    {
        $alert = $this->loadAlert($event->alertId);

        if ($alert === null) {
            return;
        }

        $this->guarded($alert, function () use ($alert): void {
            $targets = $this->resolver->previouslyNotified($alert);

            AlertNotification::query()
                ->forAlert($alert)
                ->ofPurpose(NotificationPurpose::SosTriggered, NotificationPurpose::SosUnacknowledged)
                ->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Failed])
                ->get()
                ->each(static fn (AlertNotification $row) => $row->markSkipped(AlertNotification::SKIP_CANCELLED));

            if ($targets !== []) {
                $this->dispatcher->dispatch($alert, NotificationPurpose::SosCancelled, $targets, DispatchContext::request());
            }
        });
    }
}
