<?php

declare(strict_types=1);

namespace App\Http\Resources\Network;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invitation telle qu'elle sort de l'API (P8-06, docs/API.md §17) : identifiant, statut (`en_attente`, `acceptee`,
 * `refusee`) et libellé, sens pour la personne qui lit (`received` · `sent`), **l'autre personne** seulement
 * (NetworkPersonPayload, sans donnée privée), dates, et gestes permis (`can` : accepter et refuser pour la personne
 * invitée, annuler pour celle qui a invité, tant que l'invitation est en attente).
 *
 * @mixin Invitation
 */
final class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Invitation $invitation */
        $invitation = $this->resource;
        $viewer = $request->user();
        $received = $viewer instanceof User && (int) $invitation->to_user_id === (int) $viewer->getKey();
        $other = $received ? $invitation->fromUser : $invitation->toUser;
        $pending = $invitation->isPending();

        return [
            'id' => (int) $invitation->getKey(),
            'status' => $invitation->status->value,
            'status_label' => $invitation->status->label(),
            'direction' => $received ? 'received' : 'sent',
            'person' => $other !== null ? NetworkPersonPayload::for($other) : null,
            'created_at' => $invitation->created_at?->toIso8601String(),
            'responded_at' => $invitation->responded_at?->toIso8601String(),
            'can' => [
                'accept' => $received && $pending,
                'refuse' => $received && $pending,
                'cancel' => ! $received && $pending,
            ],
        ];
    }
}
