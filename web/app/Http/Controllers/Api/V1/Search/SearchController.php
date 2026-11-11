<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Application\Search\MemberSearch;
use App\Domain\Search\SearchType;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\Search\SearchHitResource;
use App\Http\Resources\Search\SearchSectionPayload;
use App\Http\Resources\Search\SearchSuggestionResource;
use Illuminate\Http\JsonResponse;

/**
 * Recherche membre (P8-13, docs/API.md §20) : `GET search?q=&type=&page=` — sans `type`, vue d'ensemble (chaque famille
 * ouverte au compte, ses premiers résultats et son total) ; avec `type`, une famille paginée (« Charger plus ») —
 * et `GET search/suggestions?q=` (suggestions instantanées de l'en-tête, forme courte). Réponses `no-store, private` :
 * ni le navigateur, ni un proxy, ni le service worker ne gardent une liste qui dit ce que la personne a cherché ; le
 * terme n'est jamais renvoyé (ni `meta`, ni liens de pagination). Terme trop court : familles vides, `meta.too_short`.
 */
final class SearchController extends ApiController
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function index(SearchRequest $request, MemberSearch $search): JsonResponse
    {
        $viewer = $request->viewer();
        $term = $request->term();
        $type = $request->type();
        $types = $search->typesFor($viewer);
        $meta = [
            'types' => array_map(static fn (SearchType $item): array => SearchSectionPayload::type($item), $types),
            'too_short' => ! $term->isEmpty() && ! $term->isSearchable(MemberSearch::minLength()),
        ];

        if ($type === null || ! in_array($type, $types, true)) {
            $sections = $search->overview($viewer, $term, (int) config('xomiton.search.overview_per_type', 4));

            return $this->data(SearchSectionPayload::all($sections, $request), meta: $meta)->withHeaders(self::PRIVATE_HEADERS);
        }

        $page = $search->page($viewer, $term, $type, (int) config('xomiton.search.per_page', 10), $request->page());

        return $this->data(
            array_values(SearchHitResource::collection(collect($page->items()))->resolve($request)),
            meta: $meta + [
                'type' => $type->value,
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        )->withHeaders(self::PRIVATE_HEADERS);
    }

    public function suggestions(SearchRequest $request, MemberSearch $search): JsonResponse
    {
        $viewer = $request->viewer();
        $term = $request->term();
        $sections = array_values(array_filter(
            $search->overview($viewer, $term, (int) config('xomiton.search.suggestions_per_type', 3)),
            static fn ($section): bool => $section->total > 0,
        ));

        return $this->data(SearchSectionPayload::all($sections, $request, SearchSuggestionResource::class), meta: [
            'too_short' => ! $term->isEmpty() && ! $term->isSearchable(MemberSearch::minLength()),
        ])->withHeaders(self::PRIVATE_HEADERS);
    }
}
