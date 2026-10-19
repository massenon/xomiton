<?php

declare(strict_types=1);

namespace App\Http\Resources\Peer;

use App\Models\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Règle de validation active (P9-02, `GET pair/validation-rules`, cache mobile et collecte hors ligne) : clé stable
 * (`XM.rules[key]` côté web, mêmes contrôles côté client et serveur), libellé, description, exemples, paramètres
 * utiles au client (bornes de plausibilité, champs de doublons) — sans les clés documentaires de l'administration
 * (`forms`, `available_fields`).
 *
 * @property ValidationRule $resource
 */
final class ValidationRuleResource extends JsonResource
{
    /** Clés de `params` réservées à l'écran d'administration. */
    private const ADMIN_PARAMS = ['forms', 'available_fields'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rule = $this->resource;
        $params = array_diff_key($rule->params ?? [], array_flip(self::ADMIN_PARAMS));

        return [
            'key' => $rule->key,
            'label' => $rule->label,
            'description' => $rule->description,
            'locked' => (bool) $rule->is_locked,
            'params' => $params === [] ? null : $params,
            'valid_example' => $rule->valid_example,
            'invalid_example' => $rule->invalid_example,
        ];
    }
}
