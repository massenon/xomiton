<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Data\ReportData;
use App\Application\Alerts\Results\CreatedAlert;
use App\Application\Alerts\Support\AlertRecorder;
use App\Application\Shared\MediaUploader;
use App\Application\Shared\StoredMedia;
use App\Domain\Alerts\Events\AlertCreated;
use App\Enums\AlertPriority;
use App\Enums\ContactChannel;
use App\Models\Alert;
use App\Models\AlertAttachment;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Signalement ou demande d'aide (FR-ALT-01, FR-ALT-02, P5-02) : anonymat RG-01 / RG-02, contact découplé de l'anonymat
 * (signaler-et-sos.md l.11), priorité normale (jamais lue de la requête), routage RG-04, pièces jointes déjà
 * téléversées sur le disque privé, idempotence ADR-32 (clé d'un autre type : 409), aucun appareil pour un anonyme. Émet AlertCreated après le commit (notification du contact routé et journal,
 * NotifyAlertRouted).
 */
final class CreateReport
{
    public function __construct(
        private readonly RouteAlert $route,
        private readonly AlertRecorder $recorder,
    ) {}

    public function __invoke(ReportData $data): CreatedAlert
    {
        self::ensureAttachments($data->attachments);

        $existing = $this->recorder->existing($data->idempotencyKey, $data->type);

        if ($existing !== null) {
            return new CreatedAlert($existing, true, $existing->routedContact);
        }

        $needs = AlertRecorder::needs($data->needs);
        $decision = ($this->route)($data->zoneId, $needs);
        $contact = $data->contactable ? AlertRecorder::normalizeContact($data->contact) : null;

        [$alert, $duplicate] = $this->recorder->create([
            'type' => $data->type,
            ...AlertRecorder::author($data->userId, $data->isAnonymous(), $data->deviceId),
            'category' => $data->category,
            'occurred_on' => $data->occurredOn?->toDateString(),
            'description' => trim($data->description),
            'needs' => $needs,
            'zone_id' => $data->zoneId,
            'commune_id' => $data->communeId,
            'lat' => AlertRecorder::coordinate($data->lat),
            'lng' => AlertRecorder::coordinate($data->lng),
            'location_accuracy' => $data->lat === null ? null : $data->accuracy,
            'contact' => $contact,
            'preferred_channel' => $contact === null ? null : ($data->preferredChannel ?? ContactChannel::guessFor($contact)),
            'priority' => AlertPriority::Normal,
            'routed_to' => $decision->contactId,
            'routing_fallback' => $decision->fallback,
            'source' => $data->source,
            'idempotency_key' => $data->idempotencyKey,
        ], fn (Alert $alert) => $this->attach($alert, $data->attachments));

        if ($duplicate) {
            return new CreatedAlert($alert, true, $alert->routedContact);
        }

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

        return new CreatedAlert($alert, false, $alert->routedContact, $decision);
    }

    /**
     * Preuves : sur le disque privé seulement (jamais servies publiquement, lien signé pour l'admin) et pas plus de
     * `xomiton.alerts.attachments_max` (la Form Request le valide déjà : un dépassement ici est une erreur d'appel).
     *
     * @param  list<StoredMedia>  $attachments
     */
    private static function ensureAttachments(array $attachments): void
    {
        $max = (int) config('xomiton.alerts.attachments_max', 5);

        if (count($attachments) > $max) {
            throw new InvalidArgumentException(sprintf('At most %d attachments per alert, %d given.', $max, count($attachments)));
        }

        foreach ($attachments as $media) {
            if ($media->disk !== MediaUploader::privateDisk()) {
                throw new InvalidArgumentException(sprintf('Alert attachments must be stored on the private disk, not "%s".', $media->disk));
            }
        }
    }

    /**
     * @param  list<StoredMedia>  $attachments
     */
    private function attach(Alert $alert, array $attachments): void
    {
        foreach ($attachments as $media) {
            AlertAttachment::query()->create([
                'alert_id' => $alert->getKey(),
                'kind' => AlertAttachment::kindForMime($media->mime),
                'disk' => $media->disk,
                'path' => $media->path,
                'mime' => $media->mime,
                'size' => $media->size,
            ]);
        }
    }
}
