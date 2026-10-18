<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Data\SosData;
use App\Application\Alerts\Results\CreatedAlert;
use App\Application\Alerts\Support\AlertRecorder;
use App\Application\Alerts\Support\SosOwnerToken;
use App\Domain\Alerts\Events\SosTriggered;
use App\Domain\Alerts\Exceptions\IdempotencyKeyConflict;
use App\Enums\AlertPriority;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Models\UserSetting;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SOS (FR-ALT-03, RG-01, Q39) : créé au terme du compte à rebours local, toujours critique, besoin `securite`, position
 * gardée seulement si la personne la partage, routage RG-04 (sans département : point focal national), idempotence
 * ADR-32 (clé déjà prise par un signalement : SOS créé quand même sous une clé dérivée). Connecté·e : jamais anonyme (contacts de confiance, suivi) et dernier choix de partage mémorisé
 * (`user_settings.sos_share_gps`). Émet SosTriggered — et non AlertCreated — après le commit : les notifications
 * partent dans la requête (D7). Aucun limiteur bloquant (SOFTWARE_ARCHITECTURE §4) : journalisé pour la supervision.
 */
final class TriggerSos
{
    public const SAFETY_NEED = 'securite';

    public function __construct(
        private readonly RouteAlert $route,
        private readonly AlertRecorder $recorder,
    ) {}

    public function __invoke(SosData $data): CreatedAlert
    {
        $key = $data->idempotencyKey;

        try {
            $existing = $this->recorder->existing($key, AlertType::Sos);
        } catch (IdempotencyKeyConflict) {
            // Clé déjà prise par un signalement (bug de boîte d'envoi) : le SOS n'est jamais perdu, clé dérivée journalisée.
            $key = AlertRecorder::sosFallbackKey($key);
            $existing = $this->recorder->existing($key, AlertType::Sos);
        }

        if ($existing !== null) {
            return new CreatedAlert($existing, true, $existing->routedContact, sosToken: SosOwnerToken::for($existing));
        }

        $needs = AlertRecorder::needs([self::SAFETY_NEED, ...$data->needs]);
        $decision = ($this->route)($data->zoneId, $needs);
        $located = $data->keepsLocation();
        $phone = AlertRecorder::normalizeContact($data->phone);
        $message = $data->message === null ? '' : trim($data->message);

        $attributes = [
            'type' => AlertType::Sos,
            ...AlertRecorder::author($data->userId, false, $data->deviceId),
            'category' => null,
            'description' => $message === '' ? null : $message,
            'needs' => $needs,
            'zone_id' => $data->zoneId,
            'commune_id' => $data->communeId,
            'lat' => $located ? AlertRecorder::coordinate($data->lat) : null,
            'lng' => $located ? AlertRecorder::coordinate($data->lng) : null,
            'location_accuracy' => $located ? $data->accuracy : null,
            'contact' => $phone,
            'preferred_channel' => $phone === null ? null : ContactChannel::Call,
            'priority' => AlertPriority::Critical,
            'routed_to' => $decision->contactId,
            'routing_fallback' => $decision->fallback,
            'source' => $data->source,
            'idempotency_key' => $key,
        ];

        try {
            [$alert, $duplicate] = $this->recorder->create($attributes);
        } catch (IdempotencyKeyConflict) {
            [$alert, $duplicate] = $this->recorder->create([...$attributes, 'idempotency_key' => AlertRecorder::sosFallbackKey($key)]);
        }

        if ($duplicate) {
            return new CreatedAlert($alert, true, $alert->routedContact, sosToken: SosOwnerToken::for($alert));
        }

        $this->rememberChoice($data);

        Log::info('alerts.sos_created', ['ulid' => $alert->ulid]);

        event(new SosTriggered(
            alertId: (int) $alert->getKey(),
            userId: $alert->user_id,
            zoneId: $alert->zone_id,
            communeId: $alert->commune_id,
            hasLocation: $located,
            routedToContactId: $alert->routed_to,
            occurredAt: new DateTimeImmutable,
        ));

        return new CreatedAlert($alert, false, $alert->routedContact, $decision, SosOwnerToken::for($alert));
    }

    /** Dernier choix « Partager ma position » (P5-05) ; jamais bloquant pour l'envoi du SOS. */
    private function rememberChoice(SosData $data): void
    {
        if ($data->userId === null || $data->shareLocation === null) {
            return;
        }

        try {
            UserSetting::query()->updateOrCreate(['user_id' => $data->userId], ['sos_share_gps' => $data->shareLocation]);
        } catch (Throwable $exception) {
            Log::warning('alerts.sos_share_gps_not_saved', ['error' => $exception->getMessage()]);
        }
    }
}
