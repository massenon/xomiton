<?php

declare(strict_types=1);

namespace App\Application\Alerts\Queries;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * SOS d'un compte retrouvé par son identifiant public (page `/sos?alerte=`, P5-05 / P5-08) : après l'envoi depuis le
 * bouton flottant, ou au rechargement de la page, la personne connectée retrouve son alerte (statut, personnes
 * prévenues, repentir, « Je suis en sécurité »). Jamais l'alerte d'un autre compte ni un SOS anonyme : null, sans
 * distinguer « inexistant » et « pas à toi ». Lecture seule (ADR-29).
 */
final class FindOwnSos
{
    public function __invoke(User $user, mixed $ulid): ?Alert
    {
        if (! is_string($ulid) || ! Str::isUlid($ulid)) {
            return null;
        }

        return Alert::query()
            ->where('ulid', Str::upper($ulid))
            ->where('type', AlertType::Sos)
            ->where('user_id', $user->getKey())
            ->with([
                'routedContact',
                'statusChanges' => fn (HasMany $changes) => $changes->orderBy('created_at')->orderBy('id'),
            ])
            ->first();
    }
}
