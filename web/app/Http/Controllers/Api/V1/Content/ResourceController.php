<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\Queries\ListResources;
use App\Application\Content\ResourceDownload;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Content\ListResourcesRequest;
use App\Http\Resources\Content\ResourceCardResource;
use App\Support\LocaleFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/ressources (P6-08, groupe public d'ADR-31, docs/API.md §11) : catalogue de `/ressources` (ListResources,
 * P4-03) paginé par page, et lien de téléchargement signé (ResourceDownload).
 *
 *  - liste : `?q=&type=&categorie=&page=&per_page=`, mêmes filtres et même ordre que le site, poids des fichiers ;
 *    `conditional` (ETag, 304) ;
 *  - téléchargement : ressource du catalogue qui porte un fichier, sinon 404 ; compté comme sur le site
 *    (`downloads_count`, CountableVisit) ; lien signé périssable (`xomiton.uploads.signed_url_minutes`) renvoyé en JSON,
 *    jamais gardé (`no-store` : la signature expire), `expires_at` = l'échéance de la signature ; redemander le lien
 *    (reprise, lien expiré) compte un nouveau téléchargement (Q73) ; limiteur `resource_download` (par IP, route et
 *    ressource).
 */
final class ResourceController extends ApiController
{
    public function index(ListResourcesRequest $request, ListResources $list): JsonResponse
    {
        $page = $list->page($request->categorySlug(), $request->kind(), $request->search(), $this->perPage($request), $request->page());

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        return $this->paginated($page->resources, ResourceCardResource::class, [
            'categorie' => $page->category['slug'] ?? null,
            'type' => $page->kind?->value,
            'q' => $page->search !== '' ? $page->search : null,
            'categories' => array_map(static fn (array $category): array => [
                'slug' => $category['slug'],
                'label' => $category['label'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'count' => $category['count'],
            ], $page->categories),
        ]);
    }

    public function download(Request $request, string $resource, ResourceDownload $files): JsonResponse
    {
        $found = $files->findById((int) $resource) ?? throw new NotFoundHttpException;
        $files->record($found, $request);

        $expires = $files->expires();
        $size = (int) ($found->file_size ?? 0);
        $extension = strtolower(pathinfo((string) $found->file_path, PATHINFO_EXTENSION));

        return $this->data([
            'id' => (int) $found->getKey(),
            'url' => $files->signedUrl($found, $expires),
            'expires_at' => $expires->toIso8601String(),
            'kind' => $found->kind->value,
            'file_size' => $size > 0 ? $size : null,
            'file_size_label' => $size > 0 ? LocaleFormatter::make()->fileSize($size) : null,
            'filename' => $found->slug.($extension !== '' ? '.'.$extension : ''),
        ])->withHeaders(self::NO_STORE + ['X-Robots-Tag' => 'noindex, nofollow']);
    }
}
