<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\AlertReassigned;
use App\Enums\NotificationPurpose;
use App\Models\DirectoryContact;

/**
 * AlertReassigned (P5-02) → « alerte routée » au nouveau contact seulement (P5-03, D19 : l'ancien n'est pas informé ;
 * les admins ont déjà été prévenus à la création), dans la requête de l'admin, e-mail déposé dans la boîte d'envoi.
 */
final class NotifyAlertReassigned
{
    use DispatchesAlertNotifications;

    public function __construct(private readonly AlertDispatcher $dispatcher) {}

    public function handle(AlertReassigned $event): void
    {
        $alert = $this->loadAlert($event->alertId);
        $contact = DirectoryContact::query()->active()->find($event->toContactId);

        if ($alert === null || $contact === null) {
            return;
        }

        $purpose = $alert->isSos() ? NotificationPurpose::SosTriggered : NotificationPurpose::AlertRouted;

        $this->guarded($alert, fn () => $this->dispatcher->dispatch(
            $alert,
            $purpose,
            [RecipientTarget::forDirectoryContact($contact, primary: true)],
            DispatchContext::request(),
        ));
    }
}
