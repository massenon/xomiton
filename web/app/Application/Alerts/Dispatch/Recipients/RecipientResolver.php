<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch\Recipients;

use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Résout la référence d'une ligne `alert_notifications` en destinataire au moment du rejeu ou d'un message de suivi :
 * le numéro et l'adresse en vigueur sont relus (ADR-33) ; renvoie la raison d'abandon quand la personne n'est plus
 * joignable (compte inactif, fiche supprimée ou désactivée, contact de confiance retiré).
 */
final class RecipientResolver
{
    public function resolve(AlertNotification $row, Alert $alert): RecipientTarget|string
    {
        if (($trustedId = $row->referenceId(AlertNotification::TRUSTED_RECIPIENT_PREFIX)) !== null) {
            $author = User::query()->with('profile')->find($trustedId);
            $phone = trim((string) $author?->profile?->trusted_contact_phone);

            return $author !== null && $phone !== ''
                ? RecipientTarget::trusted($author, $phone)
                : AlertNotification::SKIP_NO_PHONE;
        }

        if (($contactId = $row->referenceId(AlertNotification::CONTACT_RECIPIENT_PREFIX)) !== null) {
            $contact = DirectoryContact::query()->active()->find($contactId);

            return $contact === null ? AlertNotification::SKIP_UNAVAILABLE : RecipientTarget::forDirectoryContact($contact, self::isPrimary($alert, $contact));
        }

        $userId = $row->recipientUserId() ?? $row->user_id;
        $user = $userId === null ? null : User::query()->active()->find($userId);

        if ($user === null) {
            return AlertNotification::SKIP_UNAVAILABLE;
        }

        $contact = $row->directory_contact_id === null ? null : DirectoryContact::query()->find($row->directory_contact_id);

        if ($contact !== null) {
            $kind = $row->recipientKind();

            return RecipientTarget::account($user, $kind, $contact, self::isPrimary($alert, $contact));
        }

        return RecipientTarget::account($user, (int) $alert->user_id === (int) $user->getKey() ? RecipientKind::Author : RecipientKind::Team);
    }

    /**
     * Personnes déjà prévenues d'un SOS (au moins une remise tentée : envoyée ou en échec), une fois chacune, pour les
     * messages « fausse alerte » et « en sécurité » (P5-02) : jamais celles qu'on n'a pas encore pu joindre.
     *
     * @return list<RecipientTarget>
     */
    public function previouslyNotified(Alert $alert): array
    {
        /** @var Collection<int, AlertNotification> $rows */
        $rows = AlertNotification::query()
            ->forAlert($alert)
            ->ofPurpose(NotificationPurpose::SosTriggered, NotificationPurpose::SosUnacknowledged)
            ->whereIn('status', [DeliveryStatus::Sent, DeliveryStatus::Failed])
            ->with('directoryContact')
            ->orderBy('id')
            ->get();

        $targets = new TargetSet($alert);

        foreach ($rows->unique(static fn (AlertNotification $row): string => $row->personKey()) as $row) {
            $target = $this->resolve($row, $alert);

            if ($target instanceof RecipientTarget) {
                $targets->add($target);
            }
        }

        return $targets->all();
    }

    private static function isPrimary(Alert $alert, DirectoryContact $contact): bool
    {
        return (int) $alert->routed_to === (int) $contact->getKey()
            || $alert->escalations()->where('to_contact_id', $contact->getKey())->exists();
    }
}
