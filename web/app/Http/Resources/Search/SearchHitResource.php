<?php

declare(strict_types=1);

namespace App\Http\Resources\Search;

use App\Domain\Search\SearchType;
use App\Http\Resources\Content\ResourceCardResource;
use App\Http\Resources\Content\ThematiqueCardResource;
use App\Http\Resources\Directory\DirectoryContactResource;
use App\Http\Resources\Network\NetworkPersonPayload;
use App\Models\DirectoryContact;
use App\Models\Resource as CatalogResource;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

/**
 * Résultat de la page `/recherche` et de `GET /api/v1/search` (P8-13, docs/API.md §20) : `type` (famille,
 * App\Domain\Search\SearchType ; `icon` Bootstrap Icons pour une ressource ou une thématique) puis la forme déjà servie
 * par l'écran de la famille — carte de ressource
 * (ResourceCardResource, `GET ressources`), carte de thématique (ThematiqueCardResource, `GET thematiques`), fiche de
 * l'annuaire (DirectoryContactResource, `GET directory`, plus `url` : l'annuaire filtré sur ce nom) ou personne du
 * réseau (NetworkPersonPayload, `GET follows/suggestions` : aucune donnée privée). Jamais le terme cherché.
 *
 * @property CatalogResource|Thematique|DirectoryContact|User $resource
 */
final class SearchHitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return match (true) {
            $item instanceof CatalogResource => ['type' => SearchType::Resources->value, 'icon' => $item->kind->icon()]
                + ResourceCardResource::make($item)->resolve($request),
            $item instanceof Thematique => ['type' => SearchType::Thematiques->value, 'icon' => SearchType::Thematiques->icon()]
                + ThematiqueCardResource::make($item)->resolve($request),
            $item instanceof DirectoryContact => ['type' => SearchType::Contacts->value]
                + DirectoryContactResource::make($item)->resolve($request)
                + ['url' => route('annuaire', ['q' => (string) $item->name])],
            $item instanceof User => ['type' => SearchType::Members->value] + NetworkPersonPayload::for($item),
            default => throw new InvalidArgumentException('Résultat de recherche inattendu : '.get_debug_type($item)),
        };
    }
}
