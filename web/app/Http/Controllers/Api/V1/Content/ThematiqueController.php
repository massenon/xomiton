<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\Queries\ListThematiques;
use App\Application\Content\Queries\ShowThematique;
use App\Application\Content\RecordThematiqueView;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Content\ListThematiquesRequest;
use App\Http\Resources\Content\ThematiqueCardResource;
use App\Http\Resources\Content\ThematiqueResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/thematiques (P6-08, groupe public d'ADR-31, docs/API.md §11) : mêmes requêtes que les pages publiques
 * `/thematiques` (ListThematiques, P4-02) et `/thematiques/{slug}` (ShowThematique). Lectures conditionnelles
 * (`conditional` : ETag, 304) pour le cache Hive de l'application.
 *
 *  - liste : fiches publiées, `?categorie=` inconnue ou inactive → 404, `?q=` ; toutes les fiches jusqu'à
 *    `xomiton.content.max_per_load` (`meta.has_more` au-delà), puces de catégorie et leurs compteurs en `meta` ;
 *  - fiche : publiée sinon 404 ; consultation comptée comme sur le site (RecordThematiqueView) mais seulement au premier
 *    chargement par l'appareil — une revalidation (`If-None-Match`) n'est pas une nouvelle lecture.
 */
final class ThematiqueController extends ApiController
{
    public function index(ListThematiquesRequest $request, ListThematiques $list): JsonResponse
    {
        $limit = max(1, (int) config('xomiton.content.max_per_load', 300));
        $listing = $list($request->categorySlug(), $request->search(), $limit);

        if ($listing === null) {
            throw new NotFoundHttpException;
        }

        $listedIds = array_map(static fn (array $category): int => (int) $category['id'], $listing->categories);
        $items = ThematiqueCardResource::listed($listing->thematiques, $listedIds);

        return $this->data(ThematiqueCardResource::collection($items)->resolve($request), meta: [
            'total' => $listing->total,
            'total_all' => $listing->totalAll,
            'has_more' => $listing->hasMore(),
            'categorie' => $listing->category['slug'] ?? null,
            'q' => $listing->search !== '' ? $listing->search : null,
            'categories' => array_map(static fn (array $category): array => [
                'slug' => $category['slug'],
                'label' => $category['label'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'count' => $category['count'],
            ], $listing->categories),
        ]);
    }

    public function show(Request $request, string $slug, ShowThematique $show, RecordThematiqueView $recordView): JsonResponse
    {
        $sheet = $show($slug);

        if (! $request->headers->has('If-None-Match')) {
            $recordView($sheet->thematique, $request);
        }

        return $this->respond(ThematiqueResource::make($sheet));
    }
}
