<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Application\Alerts\Dispatch\NotifiedPeople;
use App\Application\Alerts\Dispatch\NotifiedPerson;
use App\Application\Shared\Referentials;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\AlertStatus;
use App\Http\Resources\Alerts\AlertResource;
use App\Http\Resources\Alerts\NotifiedPersonResource;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AlertNote;
use App\Models\AlertStatusChange;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alerte vue par l'admin d'astreinte sur mobile (`admin/alerts`, P15-16). **Liste** (`summary`) : type, statut, priorité,
 * lieu transmis (commune, sinon département), âge, accusé, jamais le récit, le contact ni la position. **Détail** : récit,
 * moyen de contact, notes internes, historique, escalades et **position ARRONDIE** (décision client du 2026-10-29 :
 * `xomiton.alerts.admin_position_decimals`, 2 décimales ≈ 1 km — la position exacte n'est jamais donnée à l'admin mobile),
 * jamais le code de suivi (secret de la personne), la clé d'idempotence ni le compte de l'auteur·e (Alert::authorLabel).
 *
 * @property Alert $resource
 */
final class AdminAlertResource extends JsonResource
{
    private bool $detail = false;

    public function detail(): static
    {
        $this->detail = true;

        return $this;
    }

    /**
     * Coordonnées arrondies (précision de la décision client) ; null sans position.
     *
     * @return array{lat: float, lng: float, precision_m: int, rounded: bool}|null
     */
    public static function roundedPosition(Alert $alert): ?array
    {
        $coordinates = $alert->coordinates();

        if ($coordinates === null) {
            return null;
        }

        $decimals = max(0, min(4, (int) config('xomiton.alerts.admin_position_decimals', 2)));

        return [
            'lat' => round($coordinates['lat'], $decimals),
            'lng' => round($coordinates['lng'], $decimals),
            // Ordre de grandeur de l'incertitude, en mètres (1 degré ≈ 111 km).
            'precision_m' => (int) round(111_000 / (10 ** $decimals)),
            'rounded' => true,
        ];
    }

    /**
     * Personnes prévenues (dont l'équipe : admins de garde et pair·es destinataires du push) : libellé public, rôle, état
     * de remise (envoyé, vu, abandonné…), jamais le nom d'un·e admin, une référence interne, un endpoint push ni le
     * numéro du contact de confiance de l'auteur·e.
     *
     * @return list<array<string, mixed>>
     */
    private static function notified(Alert $alert, Request $request): array
    {
        return NotifiedPeople::for($alert)->map(static function (NotifiedPerson $person) use ($request): array {
            $row = NotifiedPersonResource::make($person)->resolve($request);

            if ($person->kind === RecipientKind::Trusted) {
                $row['phone'] = null;
            }

            return $row;
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->resource;
        $viewer = $request->user();
        $active = ! $alert->isCancelled() && $alert->status !== AlertStatus::Closed;
        $can = static fn (string $ability): bool => $active && $viewer instanceof User && $viewer->can($ability, $alert);

        $data = [
            'id' => $alert->ulid,
            'code' => $alert->displayCode(),
            'type' => AlertResource::apiType($alert->type),
            'type_label' => $alert->type->label(),
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'priority' => $alert->priority->value,
            'priority_label' => $alert->priority->label(),
            'place' => $alert->placeLabel(),
            'zone' => $alert->zone === null ? null : ['id' => (int) $alert->zone->getKey(), 'name' => $alert->zone->name],
            'commune' => $alert->commune === null ? null : ['id' => (int) $alert->commune->getKey(), 'name' => $alert->commune->name],
            'routed_to' => $alert->routedContact === null ? null : ['id' => (int) $alert->routedContact->getKey(), 'name' => $alert->routedContact->name],
            'acknowledged' => $alert->isAcknowledged(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'escalated' => $alert->escalated_at !== null,
            'cancelled' => $alert->isCancelled(),
            'safe' => $alert->isSafe(),
            'created_at' => $alert->created_at?->toIso8601String(),
            'age_minutes' => $alert->created_at === null ? null : (int) $alert->created_at->diffInMinutes(now(), true),
        ];

        if (! $this->detail) {
            return $data;
        }

        $referentials = app(Referentials::class);
        $category = $alert->category === null ? null : collect($referentials->violenceTypes())->firstWhere('slug', $alert->category);
        $needs = collect($referentials->needs())->keyBy('slug');

        return $data + [
            'category' => $alert->category,
            'category_label' => is_array($category) ? $category['label'] : null,
            'needs' => array_map(static fn (string $slug): array => ['slug' => $slug, 'label' => $needs->get($slug)['label'] ?? $slug], $alert->needSlugs()),
            'occurred_on' => $alert->occurred_on?->format('Y-m-d'),
            'description' => $alert->description,
            'author' => $alert->authorLabel(),
            'anonymous' => (bool) $alert->is_anonymous,
            'contact' => $alert->contact,
            'preferred_channel' => $alert->preferred_channel?->value,
            'position' => self::roundedPosition($alert),
            'notified' => self::notified($alert, $request),
            'closed_at' => $alert->closed_at?->toIso8601String(),
            'history' => $alert->relationLoaded('statusChanges')
                ? $alert->statusChanges->sortBy('id')->map(static fn (AlertStatusChange $change): array => [
                    'status' => $change->to_status->value,
                    'status_label' => $change->to_status->label(),
                    'at' => $change->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'notes' => $alert->relationLoaded('notes')
                ? $alert->notes->sortBy('id')->map(static fn (AlertNote $note): array => [
                    'id' => (int) $note->getKey(),
                    'author' => $note->relationLoaded('author') ? $note->author?->pseudo : null,
                    'body' => $note->body,
                    'at' => $note->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'escalations' => $alert->relationLoaded('escalations')
                ? $alert->escalations->sortBy('id')->map(static fn (AlertEscalation $escalation): array => [
                    'reason' => $escalation->reason,
                    'from_contact_id' => $escalation->from_contact_id,
                    'to_contact_id' => $escalation->to_contact_id,
                    'at' => $escalation->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'can' => [
                'acknowledge' => $can('take') && ! $alert->isAcknowledged(),
                'take' => $can('take'),
                'close' => $can('close'),
                'reassign' => $can('reassign'),
                'note' => $can('note'),
                'statuses' => $active ? array_map(static fn (AlertStatus $status): string => $status->value, $alert->status->allowedTransitions()) : [],
            ],
        ];
    }
}
