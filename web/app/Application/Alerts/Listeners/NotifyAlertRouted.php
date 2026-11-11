<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RoutedAlertRecipients;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\AlertCreated;
use App\Enums\AlertType;
use App\Enums\NotificationPurpose;
use App\Support\Audit;

/**
 * AlertCreated → journal `alerts.created` (P3-08) puis « alerte routée » (NOTIFICATIONS §2, P5-03) : contact de
 * l'annuaire ciblé et admins de garde, une fois par personne ; cloche et push dans la requête, e-mail `alerte-routee`
 * au contact seulement, déposé dans la boîte d'envoi (aucun SMTP dans la requête), jamais de SMS. Le SOS a son propre
 * écouteur (NotifySosTriggered). Enregistré par AppServiceProvider::DOMAIN_LISTENERS (hors de app/Listeners : la
 * découverte automatique le doublerait).
 */
final class NotifyAlertRouted
{
    use DispatchesAlertNotifications;

    public function __construct(
        private readonly AlertDispatcher $dispatcher,
        private readonly RoutedAlertRecipients $recipients,
    ) {}

    public function handle(AlertCreated $event): void
    {
        if ($event->type === AlertType::Sos->value) {
            return;
        }

        $alert = $this->loadAlert($event->alertId);

        if ($alert === null) {
            return;
        }

        $detail = sprintf(
            'type=%s ; priorite=%s ; routage=%s ; fiche=%s',
            $event->type,
            $event->priority,
            $event->routingFallback ?? '-',
            $event->routedToContactId ?? '-',
        );

        // Alerte anonyme (RG-01, RG-02, P5-02) : ni compte, ni IP, ni user-agent dans le journal.
        $alert->is_anonymous ? Audit::logWithoutRequest('alerts.created', $alert, $detail) : Audit::log('alerts.created', $alert, $detail, $alert->user);

        $this->guarded($alert, fn () => $this->dispatcher->dispatch(
            $alert,
            NotificationPurpose::AlertRouted,
            $this->recipients->for($alert),
            DispatchContext::request(),
        ));
    }
}
