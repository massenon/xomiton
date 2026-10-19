<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Application\Account\Profile\SensitiveDataConsent;
use App\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confidentialité du profil de la personne connectée (GET / PATCH me/privacy, P8-09) ; valeurs par défaut du
 * dictionnaire quand le profil manque (communauté, zone affichée, trouvable, GPS non partagé) ; consentement en cours aux
 * données sensibles (`sensitive_data`, dernière réponse de `consents`, P8-08).
 *
 * @mixin User
 */
final class PrivacyResource extends JsonResource
{
    /**
     * @return array{visibility: string, show_zone: bool, searchable: bool, sos_share_gps: bool, sensitive_consent: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $profile = $user->profile;
        $visibility = $profile?->visibility;

        return [
            'visibility' => $visibility instanceof ProfileVisibility ? $visibility->value : ProfileVisibility::Community->value,
            'show_zone' => (bool) ($profile->show_zone ?? true),
            'searchable' => (bool) ($profile->searchable ?? true),
            'sos_share_gps' => (bool) $user->settings?->sos_share_gps,
            'sensitive_consent' => app(SensitiveDataConsent::class)->granted($user),
        ];
    }
}
