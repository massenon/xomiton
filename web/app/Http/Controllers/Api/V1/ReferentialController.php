<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Shared\Referentials;
use App\Http\Resources\NeedResource;
use App\Http\Resources\ViolenceTypeResource;
use App\Http\Resources\ZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiels publics /api/v1/zones, violence-types, needs (P3-06, ADR-31 : groupe public throttlé). Lecture depuis le
 * cache 24 h (App\Application\Shared\Referentials) ; le middleware `cache.headers:public;max_age=3600;etag` (routes/api.php)
 * pose `Cache-Control: public, max-age=3600` et un `ETag` dérivé du contenu, et répond 304 à `If-None-Match`.
 */
final class ReferentialController extends ApiController
{
    public function __construct(private readonly Referentials $referentials) {}

    /** Départements actifs avec leurs communes actives. */
    public function zones(Request $request): JsonResponse
    {
        $zones = $this->referentials->zones();

        return $this->data(ZoneResource::collection($zones)->resolve($request), 200, ['count' => count($zones)]);
    }

    public function violenceTypes(Request $request): JsonResponse
    {
        $types = $this->referentials->violenceTypes();

        return $this->data(ViolenceTypeResource::collection($types)->resolve($request), 200, ['count' => count($types)]);
    }

    public function needs(Request $request): JsonResponse
    {
        $needs = $this->referentials->needs();

        return $this->data(NeedResource::collection($needs)->resolve($request), 200, ['count' => count($needs)]);
    }
}
