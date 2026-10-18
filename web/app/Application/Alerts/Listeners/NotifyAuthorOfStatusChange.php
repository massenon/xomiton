<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertLinks;
use App\Application\Alerts\Dispatch\NotificationBuilder;
use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Enums\AlertStatus;
use App\Models\User;
use App\Notifications\AlertStatusChanged as AlertStatusChangedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * AlertStatusChanged (P5-02) → « Ton signalement a avancé » à l'auteur·e non anonyme (cloche et push selon ses
 * préférences « alertes », P5-03). Rien pour une alerte anonyme, pour la création, ni pour un SOS passé « traité » par
 * « Je suis en sécurité » (déjà annoncé), ni pour la prise en charge d'un SOS (`nouveau` → `pris_en_charge` ou
 * `en_cours`) : AlertAcknowledged l'annonce déjà (`sos_acknowledged`, MarkAlertNotificationsSeen), une seule
 * notification par fait et par personne (NOTIFICATIONS §4.2). Notification ordinaire (canaux de la Phase 3) : un statut change plusieurs
 * fois, l'index « une notification par objet et par personne » d'`alert_notifications` ne s'y applique pas.
 */
final class NotifyAuthorOfStatusChange
{
    use DispatchesAlertNotifications;

    public function handle(AlertStatusChanged $event): void
    {
        if ($event->authorId === null || $event->to === AlertStatus::New->value) {
            return;
        }

        $alert = $this->loadAlert($event->alertId, ['user.settings']);
        $author = $alert?->user;

        if ($alert === null || $alert->is_anonymous || ! $author instanceof User || ! $author->isActive() || (int) $author->getKey() === (int) $event->byUserId) {
            return;
        }

        if ($alert->isSos() && ($this->isTakeOver($event) || ($event->to === AlertStatus::Handled->value && $alert->isSafe()))) {
            return;
        }

        $status = AlertStatus::tryFrom($event->to);

        $this->guarded($alert, fn () => Notification::sendNow($author, new AlertStatusChangedNotification(
            alertId: (int) $alert->getKey(),
            code: NotificationBuilder::code($alert),
            statusLabel: $status?->label() ?? $event->to,
            url: AlertLinks::for($alert, RecipientTarget::author($author)),
        )));
    }

    /** Prise en charge : émise avec AlertAcknowledged (AcknowledgeAlert, ChangeAlertStatus vers un statut de prise). */
    private function isTakeOver(AlertStatusChanged $event): bool
    {
        return $event->to === AlertStatus::Acknowledged->value
            || ($event->from === AlertStatus::New->value && $event->to === AlertStatus::InProgress->value);
    }
}
