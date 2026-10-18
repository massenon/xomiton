<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\OnCallRoster;
use App\Domain\Alerts\Events\AlertCreated;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\AlertRouted;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * AlertCreated → notification AlertRouted (NOTIFICATIONS §2 : contact de l'annuaire ciblé et admin de garde, une seule
 * notification par personne) et journal `alerts.created` (P3-08). Le SOS a son propre écouteur (NotifySosTriggered).
 * Enregistré par AppServiceProvider::DOMAIN_LISTENERS (hors de app/Listeners : la découverte automatique le doublerait).
 */
final class NotifyAlertRouted
{
    public function __construct(private readonly OnCallRoster $roster) {}

    public function handle(AlertCreated $event): void
    {
        if ($event->type === AlertType::Sos->value) {
            return;
        }

        $alert = Alert::query()->with(['zone', 'routedContact.user'])->find($event->alertId);

        if ($alert === null) {
            return;
        }

        Audit::log('alerts.created', $alert, sprintf(
            'type=%s ; priorite=%s ; routage=%s ; contact=%s',
            $event->type,
            $event->priority,
            $event->routingFallback ?? '-',
            $event->routedToContactId ?? '-',
        ), null);

        $recipients = $this->recipients($alert);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AlertRouted(
            alertId: (int) $alert->getKey(),
            reference: (string) $alert->ulid,
            typeLabel: $alert->type instanceof AlertType ? $alert->type->label() : (string) $event->type,
            zoneLabel: $alert->zone?->name ?? '—',
            receivedAt: $alert->created_at ?? $event->occurredAt,
        ));
    }

    /**
     * Compte lié à la fiche d'annuaire routée, puis admins de garde ; chaque personne une seule fois (NOTIFICATIONS §4.2).
     *
     * @return Collection<int, User>
     */
    private function recipients(Alert $alert): Collection
    {
        $recipients = new Collection;

        $contactUser = $alert->routedContact?->user;

        if ($contactUser instanceof User && $contactUser->isActive()) {
            $recipients->push($contactUser);
        }

        return $recipients->merge($this->roster->adminsOnCall())->unique(fn (User $user): int => (int) $user->getKey())->values();
    }
}
