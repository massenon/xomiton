<?php

declare(strict_types=1);

namespace App\Http\Resources\Content;

use App\Models\Resource;
use App\Support\LocaleFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource du catalogue (P6-08 : `GET ressources`, ressource principale d'une fiche) : type, **poids du fichier**
 * (octets et libellé « 1,2 Mo », réseau mobile limité : simulateur `ressources`), durée, disponibilité hors connexion,
 * catégorie (seulement si c'est un filtre de la liste, ListResources), lien public et, pour un fichier, adresse de
 * l'API qui délivre le lien signé (`download_url`, jamais le lien signé lui-même : il expirerait dans le cache Hive).
 * Un lien externe n'est rendu que s'il est une adresse web `http(s)` (Resource::isExternal).
 *
 * @property \App\Models\Resource $resource
 */
final class ResourceCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $category = $resource->relationLoaded('category') ? $resource->category : null;
        $size = $resource->file_size !== null && (int) $resource->file_size > 0 ? (int) $resource->file_size : null;
        $downloadable = $resource->isDownloadable();

        return [
            'id' => (int) $resource->getKey(),
            'slug' => (string) $resource->slug,
            'title' => (string) $resource->title,
            'kind' => $resource->kind->value,
            'kind_label' => $resource->kind->label(),
            'summary' => $resource->summary,
            'category' => $category === null ? null : ThematiqueCardResource::category($category),
            'cover_url' => $resource->cover_url,
            'file_size' => $downloadable ? $size : null,
            'file_size_label' => $downloadable && $size !== null ? LocaleFormatter::make()->fileSize($size) : null,
            'duration_minutes' => $resource->duration_minutes,
            'is_offline_available' => (bool) $resource->is_offline_available,
            'downloadable' => $downloadable,
            'playable' => $resource->isPlayable(),
            'external_url' => $resource->isExternal() ? $resource->external_url : null,
            'published_at' => $resource->published_at?->toIso8601String(),
            'url' => route('ressources.show', $resource),
            'download_url' => $downloadable ? route('api.ressources.download', ['resource' => $resource->getKey()]) : null,
        ];
    }
}
