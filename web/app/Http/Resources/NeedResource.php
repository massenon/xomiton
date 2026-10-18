<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DirectoryRole;
use App\Models\Need;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Besoin exprimé dans un signalement (`GET needs`, P3-06) : `routed_role` est la valeur de DirectoryRole ciblée par le
 * routage (RG-04), null = point focal. Accepte un modèle Need ou un tableau du cache.
 */
final class NeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $need = $this->resource instanceof Need ? $this->resource->toArray() : (array) $this->resource;
        $role = $need['routed_role'] ?? null;

        return [
            'id' => (int) ($need['id'] ?? 0),
            'slug' => (string) ($need['slug'] ?? ''),
            'label' => (string) ($need['label'] ?? ''),
            'routed_role' => $role instanceof DirectoryRole ? $role->value : $role,
            'position' => (int) ($need['position'] ?? 0),
        ];
    }
}
