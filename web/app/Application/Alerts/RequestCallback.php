<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Data\CallbackData;
use App\Application\Alerts\Results\CreatedAlert;
use App\Application\Alerts\Support\AlertRecorder;
use App\Application\Shared\Referentials;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Exceptions\ContactUnavailable;
use App\Domain\Alerts\ValueObjects\NeedRoleMap;
use App\Domain\Alerts\ValueObjects\RoutingDecision;
use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use DateTimeImmutable;

/**
 * Demande de rappel depuis l'annuaire membre (annuaire.php l.9-27, P5-02, consommé par P8-12) : alerte « besoin »
 * `source = annuaire` confiée au contact choisi **sans passer par RG-04** (`routing_fallback = manual`), dans son
 * département ; besoin déduit du rôle du contact (`needs.routed_role`, sinon `autre`) ; moyen de contact obligatoire ;
 * pseudo transmis seulement si la personne le souhaite (case cochée par défaut, annuaire.php l.133). Émet AlertCreated
 * (notification AlertRouted, e-mail `alerte-routee`).
 */
final class RequestCallback
{
    public const FALLBACK_NEED = 'autre';

    public function __construct(
        private readonly AlertRecorder $recorder,
        private readonly Referentials $referentials,
    ) {}

    public function __invoke(CallbackData $data): CreatedAlert
    {
        $existing = $this->recorder->existing($data->idempotencyKey, AlertType::Need);

        if ($existing !== null) {
            return new CreatedAlert($existing, true, $existing->routedContact);
        }

        $target = DirectoryContact::query()->active()->public()->find($data->contactId);

        if ($target === null) {
            throw new ContactUnavailable;
        }

        $contact = AlertRecorder::normalizeContact($data->contact);
        $message = $data->message === null ? '' : trim($data->message);
        $need = NeedRoleMap::fromNeeds($this->referentials->needs())->needFor($target->role) ?? self::FALLBACK_NEED;
        $decision = RoutingDecision::manual((int) $target->getKey());

        [$alert, $duplicate] = $this->recorder->create([
            'type' => AlertType::Need,
            ...AlertRecorder::author($data->userId, $data->anonymous),
            'category' => null,
            'description' => $message === '' ? (string) __('alerts.callback.default_message') : $message,
            'needs' => [$need],
            'zone_id' => $target->zone_id,
            'commune_id' => $target->commune_id,
            'contact' => $contact,
            'preferred_channel' => $data->preferredChannel ?? ContactChannel::guessFor($contact),
            'priority' => AlertPriority::Normal,
            'routed_to' => $target->getKey(),
            'routing_fallback' => $decision->fallback,
            'source' => AlertSource::Directory,
            'idempotency_key' => $data->idempotencyKey,
        ]);

        if ($duplicate) {
            return new CreatedAlert($alert, true, $alert->routedContact);
        }

        $detail = sprintf('vers=#%d ; besoin=%s', (int) $target->getKey(), $need);

        // Une demande anonyme n'est jamais reliée au compte dans le journal (ni compte, ni IP).
        $alert->is_anonymous
            ? Audit::logWithoutRequest('alerts.callback_requested', $alert, $detail)
            : Audit::log('alerts.callback_requested', $alert, $detail, User::query()->find($data->userId));

        event(new AlertCreated(
            alertId: (int) $alert->getKey(),
            type: $alert->type->value,
            priority: $alert->priority->value,
            zoneId: $alert->zone_id,
            communeId: $alert->commune_id,
            routedToContactId: $alert->routed_to,
            routingFallback: $decision->fallback?->value,
            occurredAt: new DateTimeImmutable,
        ));

        return new CreatedAlert($alert, false, $target, $decision);
    }
}
