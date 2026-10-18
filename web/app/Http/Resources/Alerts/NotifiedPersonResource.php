<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Application\Alerts\Dispatch\NotifiedPerson;
use App\Support\KeyedHash;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Personne prévenue (P5-03) servie par `POST sos` (201), `GET sos/{id}` (sondage) et au mobile (P15) :
 * `{ key, kind, label, role, state, state_label, phone, seen_at, updated_at }`. `key` : empreinte opaque et stable de
 * la référence (KeyedHash), identité de la ligne d'un sondage à l'autre, jamais `user:<id>` ni `contact:<id>`. Jamais de
 * référence brute, d'endpoint push, de position ni de nom d'admin ; `phone` nul pour une fiche non publique et pour l'équipe.
 *
 * @property NotifiedPerson $resource
 */
final class NotifiedPersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = $this->resource;

        return [
            'key' => self::opaqueKey($person->key),
            'kind' => $person->kind->value,
            'label' => $person->label,
            'role' => $person->role,
            'state' => $person->state->value,
            'state_label' => $person->state->label(),
            'phone' => $person->phone,
            'seen_at' => $person->seenAt?->toIso8601String(),
            'updated_at' => $person->updatedAt?->toIso8601String(),
        ];
    }

    /** Empreinte HMAC de la référence interne (`user:5`, `contact:12`, `trusted:3`) : stable, non réversible. */
    public static function opaqueKey(string $reference): string
    {
        return 'p'.KeyedHash::make('alerts.notified-person|'.$reference, 24);
    }
}
