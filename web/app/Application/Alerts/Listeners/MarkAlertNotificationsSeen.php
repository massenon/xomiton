<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Enums\NotificationPurpose;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * AlertAcknowledged (compte ou lien signé, P5-02) → état « vu » sur les lignes de la personne qui accuse (P5-03, D9 :
 * l'accusé, pas la lecture de la cloche) ; pour un SOS non anonyme, retour à l'auteur·e « :name a bien reçu ton alerte
 * et va t'appeler » (cloche et push), avec le libellé public de la personne (jamais un nom d'admin ni d'une fiche
 * non publique).
 */
final class MarkAlertNotificationsSeen
{
    use DispatchesAlertNotifications;

    public function __construct(private readonly AlertDispatcher $dispatcher) {}

    public function handle(AlertAcknowledged $event): void
    {
        $alert = $this->loadAlert($event->alertId, ['zone', 'commune', 'user.settings']);

        if ($alert === null) {
            return;
        }

        $this->guarded($alert, function () use ($alert, $event): void {
            $contact = $this->contact($event);
            $now = now();

            AlertNotification::query()
                ->forAlert($alert)
                ->whereNull('seen_at')
                ->where(static function (Builder $query) use ($event, $contact): void {
                    $query->whereRaw('1 = 0');

                    if ($event->byUserId !== null) {
                        $query->orWhere('user_id', $event->byUserId);
                    }

                    if ($contact !== null) {
                        $query->orWhere('directory_contact_id', $contact->getKey());
                    }
                })
                ->update(['seen_at' => $now, 'updated_at' => $now]);

            $author = $alert->is_anonymous ? null : $alert->user;

            if (! $alert->isSos() || ! $author instanceof User || ! $author->isActive() || (int) $author->getKey() === (int) $event->byUserId) {
                return;
            }

            $this->dispatcher->dispatch(
                $alert,
                NotificationPurpose::SosAcknowledged,
                [RecipientTarget::author($author)],
                DispatchContext::request(),
                ['by_label' => self::label($contact)],
            );
        });
    }

    /** Fiche de la personne qui accuse : celle du lien signé, sinon celle liée à son compte. */
    private function contact(AlertAcknowledged $event): ?DirectoryContact
    {
        if ($event->contactId !== null) {
            return DirectoryContact::query()->find($event->contactId);
        }

        return $event->byUserId === null ? null : DirectoryContact::query()->linkedTo($event->byUserId)->first();
    }

    private static function label(?DirectoryContact $contact): string
    {
        if ($contact === null) {
            return (string) __('alerts.notified.kind.team');
        }

        return $contact->is_public ? (string) $contact->name : $contact->role->label();
    }
}
