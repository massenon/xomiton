<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peer;

use App\Application\Cases\CaseSheet;
use App\Application\Cases\CreateCase;
use App\Application\Cases\Queries\ListCases;
use App\Application\Cases\UpdateCase;
use App\Http\Controllers\Api\V1\Alerts\ResolvesAlertSource;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Peer\ListCasesRequest;
use App\Http\Requests\Peer\StoreCaseRequest;
use App\Http\Requests\Peer\UpdateCaseRequest;
use App\Http\Resources\Peer\CaseResource;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dossiers de l'espace pair (P9-02, docs/API.md §22) : liste et détail **dans le scope** `CaseRecord::visibleTo`
 * (un pair ne voit que les dossiers qu'il a collectés ; dossier d'un autre ou code inconnu → même 404), collecte
 * (CreateCase : code attribué par le serveur, idempotente par `client_uuid`, 201 ou 200 au rejeu), modification
 * (UpdateCase). Le détail porte aussi `sheet`, la fiche de référence en cinq sections (CaseSheet, comme l'écran web). Pas de suppression : un dossier se clôt (`status: clos`) puis s'archive (administration). Réponses
 * `no-store` (notes déchiffrées dans le détail) ; avertissements non bloquants dans `meta.warnings`.
 */
final class CaseController extends ApiController
{
    use ResolvesAlertSource;

    public function index(ListCasesRequest $request, ListCases $cases): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $filters = $request->filters();
        $page = $cases->page($viewer, $filters, $this->perPage($request), $request->page());

        return $this->paginated($page, CaseResource::class, ['filters' => $filters->toArray()])
            ->withHeaders(self::NO_STORE);
    }

    public function store(StoreCaseRequest $request, CreateCase $create): JsonResponse
    {
        /** @var User $collector */
        $collector = $request->user();
        $result = $create($collector, $request->toInput(), $request->clientUuid(), $this->alertSource($request), $this->alertDevice($request));
        $resource = $this->resource($result->case);
        $meta = ['warnings' => $result->warningsArray(), 'duplicate' => ! $result->created];

        return $result->created
            ? $this->created($resource, route('api.pair.cases.show', ['code' => $result->case->code]), $meta)->withHeaders(self::NO_STORE)
            : $this->respond($resource, 200, $meta, headers: self::NO_STORE);
    }

    public function show(Request $request, string $code, ListCases $cases, CaseSheet $sheet): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $case = $cases->find($viewer, $code) ?? throw new NotFoundHttpException;

        return $this->respond($this->resource($case)->withSheet($sheet($viewer, $case)), headers: self::NO_STORE);
    }

    public function update(UpdateCaseRequest $request, UpdateCase $update): JsonResponse
    {
        /** @var User $by */
        $by = $request->user();
        $result = $update($by, $request->caseRecord(), $request->toChanges());

        return $this->respond($this->resource($result->case), 200, ['warnings' => $result->warningsArray()], headers: self::NO_STORE);
    }

    private function resource(CaseRecord $case): CaseResource
    {
        $case->loadMissing(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label', 'followups.author:id,pseudo']);

        return CaseResource::make($case)->withNotes();
    }
}
