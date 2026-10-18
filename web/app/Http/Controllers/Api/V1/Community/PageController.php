<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Application\Community\Pages\CreatePage;
use App\Application\Community\Pages\FollowPage;
use App\Application\Community\Pages\PageDirectory;
use App\Http\Requests\Api\Community\ListPagesRequest;
use App\Http\Requests\Api\Community\StorePageRequest;
use App\Http\Resources\Community\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * /api/v1/pages (P8-03, docs/API.md §14) : liste des pages approuvées (onglets Toutes · Abonnements · Mes pages,
 * catégorie, recherche, pagination par page), création (201, **en attente d'approbation** : visible de sa seule
 * propriétaire et de la modération, P12-05), fiche (404 pour une page non approuvée d'une autre personne), suivre /
 * ne plus suivre (idempotent, page approuvée seulement : 403).
 */
final class PageController extends CommunityApiController
{
    public function __construct(private readonly PageDirectory $pages) {}

    public function index(ListPagesRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $paginator = $this->pages->paginate($viewer, $request->tab(), $request->category(), $request->term(), $this->perPage($request, (int) config('xomiton.community.pages_per_page', 12)));
        $followed = $this->pages->followed($viewer, $paginator->items());

        $items = array_map(
            static fn (Page $page): array => PageResource::make($page)->withFollowing(isset($followed[(int) $page->getKey()]))->resolve($request),
            $paginator->items(),
        );

        return $this->page($paginator, $items, [
            'tab' => $request->tab(),
            'category' => $request->category(),
            'categories' => $this->pages->categories(),
        ]);
    }

    public function store(StorePageRequest $request, CreatePage $create): JsonResponse
    {
        $page = $create($this->viewer($request), $request->toDraft());
        $resource = PageResource::make($page)->withFollowing(true);

        return $this->created($resource, (string) $resource->resolve($request)['url']);
    }

    public function show(Request $request, Page $page): JsonResponse
    {
        return $this->respond(PageResource::make($this->visiblePage($request, $page)));
    }

    public function follow(Request $request, FollowPage $follow, Page $page): JsonResponse
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('follow', $this->visiblePage($request, $page));

        return $this->data($follow->follow($viewer, $page));
    }

    public function unfollow(Request $request, FollowPage $follow, Page $page): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->visiblePage($request, $page);

        return $this->data($follow->unfollow($viewer, $page));
    }
}
