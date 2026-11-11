<?php

declare(strict_types=1);

namespace App\Http\Resources\Peer;

use App\Application\Shared\Referentials;
use App\Enums\AlertStatus;
use App\Http\Resources\Alerts\AlertResource;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alerte routée vers moi, en détail (P9-02, `GET pair/alerts/{id}` et réponses des actions) : ce qu'il faut pour
 * répondre — type, besoin(s), type de violence, récit, lieu (commune, département), position d'un SOS, moyen de
 * contact et canal préféré, pseudonyme sauf alerte anonyme (RG-01, RG-02 : Alert::authorLabel), statut, accusé,
 * historique, gestes permis. Récit, position et contact sont chiffrés au repos et réservés au contact routé et à
 * l'administration (Alert::$hidden) : réponses `no-store`. Jamais le code de suivi (secret de la personne), la clé
 * d'idempotence, le compte de l'autrice ou de l'auteur, ni l'appareil.
 *
 * @property Alert $resource
 */
final class PeerAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->resource;
        $viewer = $request->user();
        $referentials = app(Referentials::class);
        $category = $alert->category === null ? null : collect($referentials->violenceTypes())->firstWhere('slug', $alert->category);
        $needs = collect($referentials->needs())->keyBy('slug');
        $coordinates = $alert->coordinates();
        $active = ! $alert->isCancelled() && $alert->status !== AlertStatus::Closed;

        return [
            'id' => $alert->ulid,
            'type' => AlertResource::apiType($alert->type),
            'type_label' => $alert->type->label(),
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'priority' => $alert->priority->value,
            'priority_label' => $alert->priority->label(),
            'category' => $alert->category,
            'category_label' => is_array($category) ? $category['label'] : null,
            'needs' => array_map(static fn (string $slug): array => [
                'slug' => $slug,
                'label' => $needs->get($slug)['label'] ?? $slug,
            ], $alert->needSlugs()),
            'occurred_on' => $alert->occurred_on?->format('Y-m-d'),
            'description' => $alert->description,
            'author' => $alert->authorLabel(),
            'anonymous' => (bool) $alert->is_anonymous,
            'contact' => $alert->contact,
            'preferred_channel' => $alert->preferred_channel?->value,
            'preferred_channel_label' => $alert->preferred_channel?->label(),
            'place' => $alert->placeLabel(),
            'zone' => $alert->zone === null ? null : ['id' => (int) $alert->zone->getKey(), 'name' => $alert->zone->name],
            'commune' => $alert->commune === null ? null : ['id' => (int) $alert->commune->getKey(), 'name' => $alert->commune->name],
            'position' => $coordinates === null ? null : $coordinates + ['accuracy' => $alert->location_accuracy],
            'acknowledged' => $alert->isAcknowledged(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'cancelled' => $alert->isCancelled(),
            'safe' => $alert->isSafe(),
            'created_at' => $alert->created_at?->toIso8601String(),
            'closed_at' => $alert->closed_at?->toIso8601String(),
            'history' => $alert->relationLoaded('statusChanges')
                ? $alert->statusChanges->sortBy('id')->map(static fn (AlertStatusChange $change): array => [
                    'status' => $change->to_status->value,
                    'status_label' => $change->to_status->label(),
                    'at' => $change->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'can' => [
                'acknowledge' => $active && ! $alert->isAcknowledged() && $viewer instanceof User && $viewer->can('take', $alert),
                'take' => $active && $viewer instanceof User && $viewer->can('take', $alert),
                'close' => $active && $viewer instanceof User && $viewer->can('close', $alert),
                'statuses' => $active ? array_map(
                    static fn (AlertStatus $status): string => $status->value,
                    $alert->status->allowedTransitions(),
                ) : [],
            ],
        ];
    }
}
