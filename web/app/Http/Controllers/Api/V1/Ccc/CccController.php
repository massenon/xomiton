<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ccc;

use App\Application\Ccc\BrowseCcc;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Ccc\CccToolResource;
use App\Models\CccTool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Outils CCC en lecture (P8-12, docs/API.md §19 bis ; prototype ccc.php) : `GET ccc` (outils **publiés** par famille
 * `manuel`, `boites_images`, `bd`, plus les textes de loi publiés en lien vers `/lois`) et `GET ccc/{key}` (un outil publié ;
 * masqué ou inconnu : 404 neutre, le même). Permission `ccc_tools.view` (membre, pair·e, modération, administration ;
 * sinon 403). Réponses `no-store, private` : la lecture suit l'état de publication choisi dans `/admin/ccc`.
 */
final class CccController extends ApiController
{
    public function index(Request $request, BrowseCcc $ccc): JsonResponse
    {
        $this->authorizeView($request);

        $families = array_map(
            /** @param Collection<int, CccTool> $tools */
            static fn (Collection $tools): array => CccToolResource::collection($tools)->resolve($request),
            $ccc->families(),
        );

        return $this->respond($families, 200, [
            'laws' => ['count' => $ccc->publishedLaws(), 'url' => route('lois')],
        ], [], self::NO_STORE);
    }

    public function show(Request $request, BrowseCcc $ccc, string $key): JsonResponse
    {
        $this->authorizeView($request);

        $tool = $ccc->find($key) ?? throw new ModelNotFoundException;

        return $this->respond(CccToolResource::make($tool), 200, [], [], self::NO_STORE);
    }

    private function authorizeView(Request $request): void
    {
        if ($request->user()?->can('ccc_tools.view') !== true) {
            throw new AuthorizationException;
        }
    }
}
