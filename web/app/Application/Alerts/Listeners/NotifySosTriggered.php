<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\SosRecipients;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\SosTriggered;
use App\Enums\NotificationPurpose;
use App\Models\AuditLog;
use App\Support\Audit;

/**
 * SosTriggered → journal `alerts.sos_triggered` (P3-08) puis notifications dans la requête (P5-03, D7) : contact de
 * confiance par SMS, contact routé, point focal de zone, admin de garde ; cloche, push et SMS de secours, consignés
 * dans `alert_notifications` (AlertDispatcher). Jamais la personne en danger elle-même ; la position n'est jamais
 * transmise, seul le libellé du lieu l'est. Enregistré par AppServiceProvider::DOMAIN_LISTENERS.
 */
final class NotifySosTriggered
{
    use DispatchesAlertNotifications;

    public function __construct(
        private readonly AlertDispatcher $dispatcher,
        private readonly SosRecipients $recipients,
    ) {}

    public function handle(SosTriggered $event): void
    {
        $alert = $this->loadAlert($event->alertId);

        if ($alert === null) {
            return;
        }

        $detail = sprintf(
            'zone=%s ; position=%s ; anonyme=%s',
            $event->zoneId ?? '-',
            $event->hasLocation ? 'oui' : 'non',
            $event->isAnonymous() ? 'oui' : 'non',
        );

        // Jamais d'IP ni de user-agent pour un SOS, même envoyé depuis un compte (la position d'une personne en danger ne se
        // déduit pas du journal) ; le compte est désigné par son numéro, sans adresse e-mail (SOS anonyme : ni l'un ni
        // l'autre, P5-02).
        Audit::logWithoutRequest('alerts.sos_triggered', $alert, $detail, $event->isAnonymous() || $alert->user_id === null
            ? AuditLog::ACTOR_ANONYMOUS
            : 'compte#'.$alert->user_id);

        $this->guarded($alert, fn () => $this->dispatcher->dispatch(
            $alert,
            NotificationPurpose::SosTriggered,
            $this->recipients->for($alert),
            DispatchContext::request(),
            ['has_location' => $event->hasLocation],
        ));
    }
}
