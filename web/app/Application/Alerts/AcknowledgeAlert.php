<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Results\AcknowledgementResult;
use App\Application\Alerts\Support\AcknowledgeLink;
use App\Application\Alerts\Support\AlertTransitions;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Exceptions\AcknowledgeLinkInvalid;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use DateTimeImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Accusé de réception (P5-02) : par un compte habilité (`AlertPolicy::take`) ou par le lien signé d'un contact
 * d'annuaire sans compte. Pose `acknowledged_at` (jamais effacé), ce qui arrête l'escalade RG-05, et fait passer
 * l'alerte `nouveau → pris_en_charge` (un statut plus avancé est conservé). Idempotent : une alerte déjà accusée
 * n'est pas réécrite ; une alerte annulée ou close n'attend plus d'accusé.
 */
final class AcknowledgeAlert
{
    public function byUser(Alert $alert, User $by): AcknowledgementResult
    {
        Gate::forUser($by)->authorize('take', $alert);

        return $this->acknowledge($alert, $by, null);
    }

    public function bySignedLink(Alert $alert, DirectoryContact $contact): AcknowledgementResult
    {
        if (! AcknowledgeLink::matches($alert, $contact)) {
            throw new AcknowledgeLinkInvalid;
        }

        return $this->acknowledge($alert, null, $contact);
    }

    private function acknowledge(Alert $alert, ?User $by, ?DirectoryContact $contact): AcknowledgementResult
    {
        $userId = $by === null ? null : (int) $by->getKey();
        $contactId = $contact === null ? null : (int) $contact->getKey();

        /** @var array{0: AcknowledgementResult, 1: AlertStatus|null} $outcome */
        $outcome = AlertTransitions::locked($alert, static function (Alert $fresh) use ($userId, $contactId): array {
            if ($fresh->isCancelled() || $fresh->status === AlertStatus::Closed) {
                return [new AcknowledgementResult($fresh, closed: true), null];
            }

            if ($fresh->isAcknowledged()) {
                return [new AcknowledgementResult($fresh, alreadyAcknowledged: true), null];
            }

            $from = $fresh->status;
            $fresh->acknowledged_at = now();
            $fresh->acknowledged_by = $userId;
            $fresh->acknowledged_by_contact_id = $contactId;

            if ($from === AlertStatus::New) {
                AlertTransitions::move($fresh, AlertStatus::Acknowledged, $userId);
            }

            $fresh->save();

            return [new AcknowledgementResult($fresh), $from === AlertStatus::New ? $from : null];
        });

        [$result, $previous] = $outcome;

        if (! $result->acknowledgedNow()) {
            return $result;
        }

        $fresh = $result->alert;
        $via = $contact === null ? AlertAcknowledged::VIA_ACCOUNT : AlertAcknowledged::VIA_SIGNED_LINK;

        $contact === null
            ? Audit::log('alerts.acknowledged', $fresh, 'via='.$via, $by)
            : Audit::logWithoutRequest('alerts.acknowledged', $fresh, sprintf('via=%s ; fiche=#%d', $via, (int) $contact->getKey()));

        event(new AlertAcknowledged(
            alertId: (int) $fresh->getKey(),
            type: $fresh->type->value,
            byUserId: $userId,
            contactId: $contact === null ? null : (int) $contact->getKey(),
            via: $via,
            occurredAt: new DateTimeImmutable,
        ));

        if ($previous !== null) {
            AlertTransitions::statusChanged($fresh, $previous, AlertStatus::Acknowledged, $userId);
        }

        return $result;
    }
}
