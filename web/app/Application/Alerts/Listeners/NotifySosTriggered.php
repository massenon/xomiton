<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\OnCallRoster;
use App\Domain\Alerts\Events\SosTriggered;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\SosTriggered as SosTriggeredNotification;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * SosTriggered → notification SosTriggered (NOTIFICATIONS §2 : contact de confiance par SMS, point focal de zone, admin de
 * garde, contact routé ; une seule notification par personne, jamais la personne en danger elle-même) et journal
 * `alerts.sos_triggered` (P3-08). Tout part dans la requête, sans file (D7) ; la position n'est jamais transmise, seul
 * le libellé du lieu (commune ou département) l'est. Enregistré par AppServiceProvider::DOMAIN_LISTENERS.
 */
final class NotifySosTriggered
{
    public function __construct(private readonly OnCallRoster $roster) {}

    public function handle(SosTriggered $event): void
    {
        $alert = Alert::query()->with(['zone', 'commune', 'user.profile', 'routedContact.user'])->find($event->alertId);

        if ($alert === null) {
            return;
        }

        $detail = sprintf(
            'zone=%s ; position=%s ; anonyme=%s',
            $event->zoneId ?? '-',
            $event->hasLocation ? 'oui' : 'non',
            $event->isAnonymous() ? 'oui' : 'non',
        );

        // SOS anonyme (P5-02) : ni compte, ni IP, ni user-agent dans le journal.
        $event->isAnonymous() ? Audit::logWithoutRequest('alerts.sos_triggered', $alert, $detail) : Audit::log('alerts.sos_triggered', $alert, $detail, $alert->user);

        $notification = new SosTriggeredNotification(
            alertId: (int) $alert->getKey(),
            code: (string) ($alert->tracking_code ?? $alert->ulid),
            place: $event->hasLocation ? ($alert->commune?->name ?? $alert->zone?->name) : null,
            zoneLabel: $alert->zone?->name,
            pseudo: $alert->is_anonymous ? null : $alert->authorLabel(),
        );

        $recipients = $this->recipients($alert, $event);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }

        $trustedPhone = $alert->user?->profile?->trusted_contact_phone;

        if (is_string($trustedPhone) && trim($trustedPhone) !== '') {
            Notification::route('sms', trim($trustedPhone))->notify($notification);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Alert $alert, SosTriggered $event): Collection
    {
        $recipients = new Collection;

        foreach ([$alert->routedContact?->user, $alert->zone !== null ? $this->roster->zoneFocal($alert->zone)?->user : null] as $user) {
            if ($user instanceof User && $user->isActive()) {
                $recipients->push($user);
            }
        }

        return $recipients
            ->merge($this->roster->adminsOnCall())
            ->reject(fn (User $user): bool => $event->userId !== null && (int) $user->getKey() === $event->userId)
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }
}
