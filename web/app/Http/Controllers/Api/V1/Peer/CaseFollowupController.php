<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peer;

use App\Application\Cases\AddFollowup;
use App\Application\Cases\Queries\ListCases;
use App\Application\Cases\UpdateFollowup;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Peer\StoreFollowupRequest;
use App\Http\Requests\Peer\UpdateFollowupRequest;
use App\Http\Resources\Peer\CaseFollowupResource;
use App\Models\CaseFollowup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Suivi des victimes, étapes d'un dossier visible (P9-02, docs/API.md §22) : frise chronologique (`case_followups.view_own`
 * sur ses dossiers), ajout (AddFollowup, idempotent par `client_uuid`), modification (UpdateFollowup). Dossier
 * invisible → 404 ; pas de suppression pour un pair (`case_followups.manage`, administration). Réponses `no-store`
 * (commentaires déchiffrés).
 */
final class CaseFollowupController extends ApiController
{
    public function index(Request $request, string $code, ListCases $cases): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $case = $cases->find($viewer, $code) ?? throw new NotFoundHttpException;

        if (! $viewer->can('viewAny', CaseFollowup::class)) {
            throw new AuthorizationException;
        }

        $followups = $case->followups()->with('author:id,pseudo')->chronological()->get();

        return $this->respond(CaseFollowupResource::collection($followups), meta: ['case' => $case->code], headers: self::NO_STORE);
    }

    public function store(StoreFollowupRequest $request, AddFollowup $add): JsonResponse
    {
        /** @var User $by */
        $by = $request->user();
        $case = $request->caseRecord();
        $result = $add($by, $case, $request->toInput(), $request->clientUuid());
        $resource = CaseFollowupResource::make($result->followup->loadMissing('author:id,pseudo'));
        $meta = ['case' => $case->code, 'duplicate' => ! $result->created];

        return $result->created
            ? $this->created($resource, route('api.pair.cases.followups.index', ['code' => $case->code]), $meta)->withHeaders(self::NO_STORE)
            : $this->respond($resource, 200, $meta, headers: self::NO_STORE);
    }

    public function update(UpdateFollowupRequest $request, UpdateFollowup $update): JsonResponse
    {
        /** @var User $by */
        $by = $request->user();
        $followup = $update($by, $request->followup(), $request->toChanges());

        return $this->respond(CaseFollowupResource::make($followup->loadMissing('author:id,pseudo')), meta: ['case' => $request->caseRecord()->code], headers: self::NO_STORE);
    }
}
