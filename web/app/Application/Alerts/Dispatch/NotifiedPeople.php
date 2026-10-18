<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Alerts\Dispatch\NotifiedState;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * « Personnes prévenues » d'une alerte (P5-03 ; consommé par `POST sos` / `GET sos/{id}` P5-06 et la page /sos P5-05) :
 * lignes `alert_notifications` du SOS (déclenchement et escalade) ou du signalement (routage et rappel), groupées par
 * personne, dans l'ordre contact routé → points focaux → autres fiches → équipe → contact de confiance. Une personne
 * dont toutes les remises ont été abandonnées (injoignable) n'apparaît pas.
 */
final class NotifiedPeople
{
    private function __construct() {}

    /**
     * @return Collection<int, NotifiedPerson>
     */
    public static function for(Alert $alert): Collection
    {
        $purposes = $alert->isSos()
            ? [NotificationPurpose::SosTriggered, NotificationPurpose::SosUnacknowledged]
            : [NotificationPurpose::AlertRouted, NotificationPurpose::AlertReminder, NotificationPurpose::AlertEscalated];

        $rows = AlertNotification::query()
            ->forAlert($alert)
            ->ofPurpose(...$purposes)
            ->with('directoryContact.zone')
            ->orderBy('id')
            ->get();

        return $rows
            ->groupBy(static fn (AlertNotification $row): string => $row->personKey())
            ->reject(static fn (Collection $group): bool => $group->every(static fn (AlertNotification $row): bool => $row->status === DeliveryStatus::Skipped && $row->seen_at === null))
            ->map(static fn (Collection $group, string $key): NotifiedPerson => self::person($alert, $key, $group))
            ->sortBy([
                static fn (NotifiedPerson $a, NotifiedPerson $b): int => (int) $b->routed <=> (int) $a->routed,
                static fn (NotifiedPerson $a, NotifiedPerson $b): int => $a->kind->rank() <=> $b->kind->rank(),
            ])
            ->values();
    }

    /**
     * Personne à appeler en premier (bouton d'appel du contact principal, `signaler-et-sos.md` l.18) : la première
     * dont le numéro peut être montré (contact routé, point focal, contact de confiance) ; aucune → « Appeler le 117 ».
     *
     * @param  Collection<int, NotifiedPerson>  $people
     */
    public static function primaryContact(Collection $people): ?NotifiedPerson
    {
        return $people->first(static fn (NotifiedPerson $person): bool => $person->phone !== null);
    }

    /**
     * @param  Collection<int, AlertNotification>  $rows
     */
    private static function person(Alert $alert, string $key, Collection $rows): NotifiedPerson
    {
        /** @var AlertNotification $first */
        $first = $rows->first();
        $kind = $first->recipientKind();
        $contact = $rows->map(static fn (AlertNotification $row): ?DirectoryContact => $row->directoryContact)->filter()->first();
        $state = NotifiedState::fromRows($rows->map(static fn (AlertNotification $row) => $row->snapshot()));
        $seenAt = $rows->pluck('seen_at')->filter()->min();
        $updatedAt = $rows->pluck('updated_at')->filter()->max();

        [$label, $role, $phone] = match (true) {
            $kind === RecipientKind::Trusted => self::trusted($alert),
            $contact instanceof DirectoryContact => self::contact($contact),
            default => [(string) __('alerts.notified.kind.team'), (string) __('alerts.notified.kind.team_role'), null],
        };

        return new NotifiedPerson(
            key: $key,
            kind: $kind,
            label: $label,
            role: $role,
            state: $state,
            phone: $phone,
            seenAt: $seenAt === null ? null : CarbonImmutable::instance($seenAt),
            updatedAt: $updatedAt === null ? null : CarbonImmutable::instance($updatedAt),
            routed: $contact instanceof DirectoryContact && (int) $contact->getKey() === (int) $alert->routed_to,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private static function trusted(Alert $alert): array
    {
        $profile = $alert->user?->profile;
        $name = trim((string) $profile?->trusted_contact_name);
        $phone = trim((string) $profile?->trusted_contact_phone);
        $role = (string) __('alerts.notified.kind.trusted');

        return [$name !== '' ? $name : $role, $role, $phone !== '' ? $phone : null];
    }

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private static function contact(DirectoryContact $contact): array
    {
        $roleLabel = $contact->role->label();
        $zone = $contact->zone?->name;
        $role = $zone === null ? $roleLabel : (string) __('alerts.notified.role_zone', ['role' => $roleLabel, 'zone' => $zone]);
        $phone = trim((string) $contact->phone);

        return [
            $contact->is_public ? (string) $contact->name : $roleLabel,
            $role,
            $contact->is_public && $phone !== '' ? $phone : null,
        ];
    }
}
