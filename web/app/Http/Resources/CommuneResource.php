<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Commune;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commune d'un département (imbriquée dans ZoneResource, P3-06). Accepte un modèle Commune ou un tableau du cache.
 */
final class CommuneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $commune = $this->resource instanceof Commune ? $this->resource->toArray() : (array) $this->resource;

        return [
            'id' => (int) ($commune['id'] ?? 0),
            'name' => (string) ($commune['name'] ?? ''),
            'slug' => (string) ($commune['slug'] ?? ''),
            'position' => (int) ($commune['position'] ?? 0),
        ];
    }
}
