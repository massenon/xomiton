<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Département (`GET zones`, P3-06) avec ses communes actives. Accepte un modèle Zone ou le tableau mis en cache par
 * App\Application\Shared\Referentials (même forme).
 */
final class ZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $zone = $this->resource instanceof Zone ? $this->resource->toArray() + ['communes' => $this->resource->communes->toArray()] : (array) $this->resource;

        return [
            'id' => (int) ($zone['id'] ?? 0),
            'name' => (string) ($zone['name'] ?? ''),
            'slug' => (string) ($zone['slug'] ?? ''),
            'position' => (int) ($zone['position'] ?? 0),
            'communes' => CommuneResource::collection(array_values((array) ($zone['communes'] ?? [])))->resolve($request),
        ];
    }
}
