<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Group;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Socle des contrôleurs des groupes et des pages (P8-02, P8-03, docs/API.md §13 et §14) : la personne connectée, le
 * groupe ou la page de la route **visible** par elle (404 sinon, jamais 403 : on ne révèle pas ce qu'on ne peut pas
 * voir) et une liste paginée par page dont les éléments sont déjà résolus (état propre à la personne).
 */
abstract class CommunityApiController extends ApiController
{
    protected function viewer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Fiche d'un groupe actif (réduite pour un groupe privé dont on n'est pas membre : GroupPolicy::discover).
     *
     * @throws NotFoundHttpException
     */
    protected function discoverable(Request $request, Group $group): Group
    {
        if (! $this->viewer($request)->can('discover', $group)) {
            throw new NotFoundHttpException;
        }

        return $group;
    }

    /**
     * Contenu d'un groupe (publications, membres) : GroupPolicy::view.
     *
     * @throws NotFoundHttpException
     */
    protected function readable(Request $request, Group $group): Group
    {
        if (! $this->viewer($request)->can('view', $group)) {
            throw new NotFoundHttpException;
        }

        return $group;
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function visiblePage(Request $request, Page $page): Page
    {
        if (! $this->viewer($request)->can('view', $page)) {
            throw new NotFoundHttpException;
        }

        return $page;
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $meta
     */
    protected function page(LengthAwarePaginator $paginator, array $items, array $meta = []): JsonResponse
    {
        $paginator->withQueryString();

        return $this->data($items, 200, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ] + $meta, [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ]);
    }
}
