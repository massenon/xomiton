<?php

declare(strict_types=1);

namespace App\Http\Resources\Search;

use App\Application\Search\SearchSection;
use App\Domain\Search\SearchType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une famille de résultats telle que la servent la page `/recherche` et l'API (P8-13, docs/API.md §20) : `type`,
 * `label`, `icon`, `total`, `more` (des résultats au-delà de ceux listés) et `items` — résultats complets
 * (SearchHitResource) ou suggestions courtes (SearchSuggestionResource).
 */
final class SearchSectionPayload
{
    private function __construct() {}

    /**
     * @param  class-string<JsonResource>  $itemResource
     * @return array{type: string, label: string, icon: string, total: int, more: bool, items: list<array<string, mixed>>}
     */
    public static function of(SearchSection $section, Request $request, string $itemResource = SearchHitResource::class): array
    {
        return self::type($section->type) + [
            'total' => $section->total,
            'more' => $section->hasMore(),
            'items' => array_values($itemResource::collection($section->items)->resolve($request)),
        ];
    }

    /**
     * @param  list<SearchSection>  $sections
     * @param  class-string<JsonResource>  $itemResource
     * @return list<array{type: string, label: string, icon: string, total: int, more: bool, items: list<array<string, mixed>>}>
     */
    public static function all(array $sections, Request $request, string $itemResource = SearchHitResource::class): array
    {
        return array_map(static fn (SearchSection $section): array => self::of($section, $request, $itemResource), $sections);
    }

    /**
     * @return array{type: string, label: string, icon: string}
     */
    public static function type(SearchType $type): array
    {
        return ['type' => $type->value, 'label' => $type->label(), 'icon' => $type->icon()];
    }
}
