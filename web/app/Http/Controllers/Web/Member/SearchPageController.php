<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Search\MemberSearch;
use App\Domain\Search\Exceptions\SearchUnavailable;
use App\Domain\Search\SearchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\Search\SearchHitResource;
use App\Http\Resources\Search\SearchSectionPayload;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Recherche » `/recherche?q=&type=` (P8-13) : page Inertia `Search/Index` (morceau chargé à la demande par
 * app.ts) dans le gabarit membre, atteinte depuis le champ de recherche de l'en-tête (XmHeader). Sans `type` : vue
 * d'ensemble par famille (ressources, thématiques, contacts, membres trouvables ; MemberSearch) ; avec `type` : une
 * famille, première page, la suite par l'API (`GET /api/v1/search?type=&page=`, docs/API.md §20). Résultats sous la
 * forme de l'API. Page d'une session : noindex (HandleInertiaRequests, `Disallow: /recherche` du robots.txt, hors
 * sitemap), jamais gardée par le service worker ; le terme ne reste ni en session (ForgetSearchTerms) ni en journal.
 * Recherche indisponible : la page s'affiche avec un message (`failed`), sans 500.
 */
final class SearchPageController extends Controller
{
    public function __invoke(SearchRequest $request, MemberSearch $search): Response
    {
        $viewer = $request->viewer();
        $term = $request->term();
        $types = $search->typesFor($viewer);
        $type = $request->type();
        $type = $type !== null && in_array($type, $types, true) ? $type : null;
        $title = (string) __('search.title');
        $sections = [];
        $pagination = null;
        $failed = false;

        try {
            if ($type === null) {
                $sections = SearchSectionPayload::all(
                    $search->overview($viewer, $term, (int) config('xomiton.search.overview_per_type', 4)),
                    $request,
                );
            } else {
                $page = $search->page($viewer, $term, $type, (int) config('xomiton.search.per_page', 10));
                $sections = [SearchSectionPayload::type($type) + [
                    'total' => $page->total(),
                    'more' => $page->hasMorePages(),
                    'items' => array_values(SearchHitResource::collection(collect($page->items()))->resolve($request)),
                ]];
                $pagination = [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ];
            }
        } catch (SearchUnavailable $e) {
            report($e);
            $failed = true;
        }

        return Inertia::render('Search/Index', [
            'title' => $title,
            'q' => $term->value(),
            'type' => $type?->value,
            'types' => array_map(static fn (SearchType $item): array => SearchSectionPayload::type($item), $types),
            'sections' => $sections,
            'pagination' => $pagination,
            'too_short' => ! $term->isEmpty() && ! $term->isSearchable(MemberSearch::minLength()),
            'failed' => $failed,
            'limits' => [
                'min_length' => MemberSearch::minLength(),
                'max_length' => SearchRequest::maxLength(),
            ],
        ])->withViewData(['title' => $title]);
    }
}
