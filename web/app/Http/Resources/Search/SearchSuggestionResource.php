<?php

declare(strict_types=1);

namespace App\Http\Resources\Search;

use App\Domain\Search\SearchType;
use App\Http\Resources\Network\NetworkPersonPayload;
use App\Models\DirectoryContact;
use App\Models\Resource as CatalogResource;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

/**
 * Suggestion instantanée du champ de recherche de l'en-tête (P8-13, `GET /api/v1/search/suggestions`, docs/API.md §20) :
 * forme courte et commune aux quatre familles, légère sur un réseau mobile — `type`, `id`, `title`, `meta` (ligne
 * secondaire), `url`, `inertia` (page de l'espace membre : visite Inertia ; sinon page publique Blade), `icon`
 * (Bootstrap Icons), `avatar_url` (membres seulement). Aucune donnée privée (membres : NetworkPersonPayload).
 *
 * @property CatalogResource|Thematique|DirectoryContact|User $resource
 */
final class SearchSuggestionResource extends JsonResource
{
    /**
     * @return array{type: string, id: int, title: string, meta: string|null, url: string, inertia: bool, icon: string, avatar_url: string|null}
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return match (true) {
            $item instanceof CatalogResource => self::row(SearchType::Resources, (int) $item->getKey(), (string) $item->title,
                self::meta([$item->kind->label(), $item->relationLoaded('category') ? $item->category?->label : null]),
                route('ressources.show', $item), false, $item->kind->icon()),
            $item instanceof Thematique => self::row(SearchType::Thematiques, (int) $item->getKey(), (string) $item->title,
                self::meta([$item->relationLoaded('category') ? $item->category?->label : null]),
                route('thematiques.show', $item), false, SearchType::Thematiques->icon()),
            $item instanceof DirectoryContact => self::row(SearchType::Contacts, (int) $item->getKey(), (string) $item->name,
                self::meta([$item->role->label(), $item->commune?->name ?? $item->zone?->name ?? __('directory.card.national')]),
                route('annuaire', ['q' => (string) $item->name]), true, SearchType::Contacts->icon()),
            $item instanceof User => self::member($item),
            default => throw new InvalidArgumentException('Suggestion inattendue : '.get_debug_type($item)),
        };
    }

    /**
     * @return array{type: string, id: int, title: string, meta: string|null, url: string, inertia: bool, icon: string, avatar_url: string|null}
     */
    private static function member(User $user): array
    {
        $person = NetworkPersonPayload::for($user);

        return self::row(SearchType::Members, $person['id'], $person['pseudo'],
            self::meta([$person['role_label'], $person['zone']['name'] ?? null]),
            $person['profile_url'] ?? route('qui-suivre'), true, SearchType::Members->icon(), $person['avatar_url']);
    }

    /**
     * @return array{type: string, id: int, title: string, meta: string|null, url: string, inertia: bool, icon: string, avatar_url: string|null}
     */
    private static function row(SearchType $type, int $id, string $title, ?string $meta, string $url, bool $inertia, string $icon, ?string $avatar = null): array
    {
        return [
            'type' => $type->value,
            'id' => $id,
            'title' => $title,
            'meta' => $meta,
            'url' => $url,
            'inertia' => $inertia,
            'icon' => $icon,
            'avatar_url' => $avatar,
        ];
    }

    /**
     * @param  list<string|null>  $parts
     */
    private static function meta(array $parts): ?string
    {
        $parts = array_values(array_filter($parts, static fn (?string $part): bool => $part !== null && trim($part) !== ''));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
