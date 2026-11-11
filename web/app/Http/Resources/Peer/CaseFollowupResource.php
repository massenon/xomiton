<?php

declare(strict_types=1);

namespace App\Http\Resources\Peer;

use App\Models\CaseFollowup;
use App\Models\User;
use App\Rules\NotFutureDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Étape de suivi d'un dossier visible (P9-02 ; frise de admin/suivi.php l.94-104) : date, étape, résultat, prochaine
 * échéance et retard, commentaire (chiffré au repos, déchiffré pour qui voit le dossier ; réponses `no-store`),
 * pseudonyme de la personne qui l'a saisie (jamais son nom).
 *
 * @property CaseFollowup $resource
 */
final class CaseFollowupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $followup = $this->resource;
        $viewer = $request->user();
        $nextOn = $followup->next_on?->format('Y-m-d');

        return [
            'id' => (int) $followup->getKey(),
            'date' => $followup->date?->format('Y-m-d'),
            'step' => $followup->step,
            'outcome' => $followup->outcome->value,
            'outcome_label' => $followup->outcome->label(),
            'outcome_badge' => $followup->outcome->badge(),
            'next_on' => $nextOn,
            'overdue' => $followup->isPending() && $nextOn !== null && $nextOn < NotFutureDate::today()->format('Y-m-d'),
            'comment' => $followup->comment,
            'author' => $followup->author?->pseudo,
            'by_me' => $viewer instanceof User && (int) $followup->by_user_id === (int) $viewer->getKey(),
            'client_uuid' => $this->when($viewer instanceof User && (int) $followup->by_user_id === (int) $viewer->getKey(), $followup->client_uuid),
            'created_at' => $followup->created_at?->toIso8601String(),
            'updated_at' => $followup->updated_at?->toIso8601String(),
        ];
    }
}
