<?php

declare(strict_types=1);

namespace App\Http\Resources\Member;

use App\Application\Member\Results\Dashboard;
use App\Enums\UserStatus;
use App\Http\Resources\Alerts\RoutedAlertResource;
use App\Http\Resources\Content\ThematiqueCardResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tableau de bord mobile (P6-08, `GET /api/v1/dashboard`) : compte réduit (salutation et bandeaux ; la vue complète
 * reste `GET me`), tuiles (clé, route de l'application, compteur), compteurs non lus, complétion du profil, fiches
 * « À la une », prochain événement (titre, dates, « en ligne » ; jamais le lieu) et section `peer` (null hors espace
 * pair). Aucun texte d'interface : les libellés vivent dans le catalogue du client (ARB, `lang:export`).
 *
 * @property Dashboard $resource
 */
final class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dashboard = $this->resource;
        $user = $dashboard->user;
        $listing = $dashboard->featured;
        $event = $dashboard->nextEvent;
        $peer = $dashboard->peer;
        $listedIds = array_map(static fn (array $category): int => (int) $category['id'], $listing->categories);

        return [
            'user' => [
                'id' => (int) $user->getKey(),
                'pseudo' => (string) $user->pseudo,
                'avatar_url' => $user->avatar_url,
                'role' => $user->getRoleNames()->first(),
                'pending_validation' => $user->status === UserStatus::Pending,
                'email_verified' => $user->email_verified_at !== null,
            ],
            'tiles' => $dashboard->tiles,
            'counters' => $dashboard->counters,
            'profile' => [
                'completion' => $dashboard->completion,
                'complete' => $dashboard->completion >= 100,
            ],
            'featured' => [
                'total' => $listing->totalAll,
                'items' => ThematiqueCardResource::collection(ThematiqueCardResource::listed($listing->thematiques, $listedIds))->resolve($request),
            ],
            'next_event' => $event === null ? null : [
                'id' => $event->ulid,
                'title' => (string) $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'is_online' => (bool) $event->is_online,
            ],
            'peer' => $peer === null ? null : [
                'kpi' => $peer['kpi'],
                'routed_alerts' => [
                    'open' => $peer['routed_alerts']['open'],
                    'new' => $peer['routed_alerts']['new'],
                    'items' => RoutedAlertResource::collection($peer['routed_alerts']['items'])->resolve($request),
                ],
                'tasks' => $peer['tasks'],
            ],
        ];
    }
}
