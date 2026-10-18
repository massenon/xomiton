<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réglages de sécurité de la personne connectée (GET / PATCH me/security, P6-06) : noms du prototype mobile.
 * Jamais de code PIN (Q37 b).
 *
 * @mixin User
 */
final class SecurityResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $settings = $user->settings;

        return [
            'discreet' => (bool) $user->profile?->discreet_mode,
            'mask_previews' => (bool) $settings?->mask_sensitive_notifications,
            'pin_enabled' => (bool) $settings?->pin_enabled,
            'neutral_tab_title' => (bool) $settings?->neutral_tab_title,
        ];
    }
}
