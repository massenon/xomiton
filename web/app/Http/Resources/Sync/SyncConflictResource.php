<?php

declare(strict_types=1);

namespace App\Http\Resources\Sync;

use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conflit de l'appareil (`GET sync/conflicts`, P9-06, ARCH §4) : sujet (type d'élément `collecte`, identifiant, code),
 * champ, état, résolution. Valeurs (chiffrées en base) servies seulement si le compte voit encore le dossier
 * (CaseRecordPolicy::view) ; jamais pour un sujet supprimé ou sorti de sa portée.
 *
 * @property SyncConflict $resource
 */
final class SyncConflictResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conflict = $this->resource;
        $subject = $conflict->subject;
        $viewer = $request->user();
        $visible = $subject instanceof CaseRecord && $viewer instanceof User && $viewer->can('view', $subject);

        return [
            'id' => (int) $conflict->getKey(),
            'subject' => [
                'type' => $subject instanceof CaseRecord ? 'collecte' : null,
                'server_id' => $visible ? (int) $subject->getKey() : null,
                'reference' => $visible ? (string) $subject->code : null,
            ],
            'field' => (string) $conflict->field,
            'status' => $conflict->status->value,
            'resolution' => $conflict->resolution,
            'server_value' => $visible ? $conflict->server_value : null,
            'device_value' => $visible ? $conflict->device_value : null,
            'resolved_value' => $visible ? $conflict->resolved_value : null,
            // Le pair tranche lui-même (serveur ou appareil) ses conflits sur ses dossiers ; `merge` : administration seulement.
            'can' => [
                'resolve' => $conflict->isOpen() && $viewer instanceof User && $viewer->can('resolve', $conflict),
                'merge' => $conflict->isOpen() && $viewer instanceof User && $viewer->can('merge', $conflict),
            ],
            'created_at' => $conflict->created_at?->toIso8601String(),
            'resolved_at' => $conflict->resolved_at?->toIso8601String(),
        ];
    }
}
