<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member\Peer;

use App\Application\Cases\CaseSheet;
use App\Application\Cases\CollectOptions;
use App\Application\Cases\Queries\ListCases;
use App\Domain\Cases\FollowupStep;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Peer\BrowseCasesPageRequest;
use App\Http\Requests\Peer\ListCasesRequest;
use App\Http\Resources\Peer\CaseResource;
use App\Models\User;
use App\Rules\NotFutureDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Écrans Vue des dossiers de la pair·e (P9-08, ROLE_ET_PERMISSIONS.md §3 : « Fiches VBG / VDH » et « Suivi des victimes »
 * 👁 sur ses dossiers) : liste `/espace-pair/dossiers` (filtres statut · nature · code, `?status=&kind=&q=&page=`) et fiche
 * `/espace-pair/dossiers/{code}` (cinq sections en lecture + frise de suivi, ajout d'une étape et changement de statut par
 * l'API `pair/`). **Toujours** par `CaseRecord::visibleTo` (ListCases) : le dossier d'un autre pair, un code inconnu ou
 * illisible donnent le même 404. Pages `no-store, private` (notes, récit et commentaires déchiffrés), jamais gardées par
 * le service worker. Réservé au compte actif `peer_space.access` (EnsurePeerSpaceAccess).
 */
final class PeerCasesPageController extends Controller
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function index(BrowseCasesPageRequest $request, ListCases $cases): Response
    {
        $viewer = $request->viewer();
        $filters = $request->filters();
        $title = (string) __('peer.cases.title');

        return Inertia::render('PeerSpace/Cases', [
            'title' => $title,
            'filters' => $filters->toArray(),
            'search_max' => ListCasesRequest::SEARCH_MAX,
            'statuses' => array_map(
                static fn (CaseStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                array_values(array_filter(CaseStatus::cases(), static fn (CaseStatus $status): bool => $status !== CaseStatus::Archived)),
            ),
            'kinds' => array_map(static fn (CaseKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], CaseKind::cases()),
            'cases' => function () use ($request, $cases, $viewer, $filters): array {
                $page = $cases->page($viewer, $filters, max(1, (int) config('xomiton.peer.cases_per_page', 15)), $request->page());

                return [
                    'data' => CaseResource::collection(collect($page->items()))->resolve($request),
                    'meta' => [
                        'current_page' => $page->currentPage(),
                        'last_page' => $page->lastPage(),
                        'per_page' => $page->perPage(),
                        'total' => $page->total(),
                    ],
                ];
            },
        ])->withViewData(['title' => $title])->toResponse($request)->withHeaders(self::PRIVATE_HEADERS);
    }

    public function show(Request $request, string $code, ListCases $cases, CaseSheet $sheet, CollectOptions $options): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $case = $cases->find($viewer, $code) ?? throw new NotFoundHttpException;
        $case->loadMissing(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label', 'followups.author:id,pseudo']);
        $title = $case->code;

        return Inertia::render('PeerSpace/CaseShow', [
            'title' => $title,
            'record' => CaseResource::make($case)->withNotes()->resolve($request),
            'sheet' => $sheet($viewer, $case),
            'steps' => $options->lists()['followup_steps'],
            'outcomes' => $options->outcomes(),
            'statuses' => array_map(
                static fn (CaseStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                array_values(array_filter(
                    $case->status->allowedTransitions(),
                    static fn (CaseStatus $status): bool => $status !== CaseStatus::Archived || $viewer->can('cases.view_all'),
                )),
            ),
            'today' => NotFutureDate::today()->format('Y-m-d'),
            'limits' => ['comment_max' => FollowupStep::COMMENT_MAX_LENGTH],
        ])->withViewData(['title' => $title])->toResponse($request)->withHeaders(self::PRIVATE_HEADERS);
    }
}
