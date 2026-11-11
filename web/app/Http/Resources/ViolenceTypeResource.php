<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ViolenceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Type de violence (`GET violence-types`, P3-06). Accepte un modèle ViolenceType ou un tableau du cache.
 */
final class ViolenceTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource instanceof ViolenceType ? $this->resource->toArray() : (array) $this->resource;

        return [
            'id' => (int) ($type['id'] ?? 0),
            'slug' => (string) ($type['slug'] ?? ''),
            'label' => (string) ($type['label'] ?? ''),
            'icon' => $type['icon'] ?? null,
            'description' => $type['description'] ?? null,
            'position' => (int) ($type['position'] ?? 0),
        ];
    }
}
