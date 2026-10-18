<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alerte vue par la personne qui l'a envoyée (réponse 201 de création, `GET sos/{id}` ; P5-06 l'étend avec
 * `notified`). Jamais la position, le contact, le récit ni la clé d'idempotence : la personne les connaît et l'API
 * ne les redonne pas (P5-02 §4.9) ; un SOS dit seulement si une position est jointe (`located`, P5-05 : « Envoyée sans
 * position GPS » après le bouton flottant ou un rechargement). `type` en valeurs API (Q29 : report | need | sos).
 *
 * @property Alert $resource
 */
final class AlertResource extends JsonResource
{
    /** Valeurs stockées → valeurs API (Q29). */
    public const API_TYPES = ['denonciation' => 'report', 'besoin' => 'need', 'sos' => 'sos'];

    private ?string $sosToken = null;

    /** Réponse de création d'un SOS anonyme : jeton `X-Sos-Token` exigé ensuite par cancel et safe (Q52 b). */
    public function withSosToken(?string $token): static
    {
        $this->sosToken = $token;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->resource;

        return [
            'id' => $alert->ulid,
            'tracking_code' => $alert->tracking_code,
            'tracking_code_display' => $alert->displayCode(),
            'type' => self::apiType($alert->type),
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'status_text' => (string) __('alerts.report.status_text.'.$alert->status->value),
            'priority' => $alert->priority->value,
            'created_at' => $alert->created_at?->toIso8601String(),
            'acknowledged' => $alert->isAcknowledged(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'cancelled' => $alert->isCancelled(),
            'safe' => $alert->isSafe(),
            // Valeur brute (chiffrée) : savoir qu'une position existe sans la déchiffrer.
            'located' => $this->when($alert->isSos(), static fn (): bool => $alert->getRawOriginal('lat') !== null && $alert->getRawOriginal('lng') !== null),
            'can_cancel_until' => $alert->isSos() && ! $alert->isCancelled() ? $alert->cancelWindowEndsAt()->toIso8601String() : null,
            'routed' => $alert->routedContact === null ? null : new RoutedContactResource($alert->routedContact),
            'sos_token' => $this->when($this->sosToken !== null, $this->sosToken),
            'history' => $alert->relationLoaded('statusChanges')
                ? $alert->statusChanges->map(static fn (AlertStatusChange $change): array => [
                    'status' => $change->to_status->value,
                    'at' => $change->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }

    public static function apiType(AlertType $type): string
    {
        return self::API_TYPES[$type->value];
    }
}
