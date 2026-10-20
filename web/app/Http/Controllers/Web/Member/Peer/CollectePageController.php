<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member\Peer;

use App\Application\Cases\CollectOptions;
use App\Application\Cases\Queries\CaseFilters;
use App\Application\Cases\Queries\ListCases;
use App\Application\Cases\ValidationCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Peer\CaseResource;
use App\Models\User;
use App\Rules\NotFutureDate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Collecte de données » `/collecte` (P9-04 ; prototype collecte.php) : page Inertia `PeerSpace/Collecte`.
 * Les listes fermées (natures, départements → communes, populations, sexes, tranches d'âge, types de violence,
 * orientations) et les règles **actives** du catalogue (même forme que `GET pair/validation-rules`) sont dans les props,
 * donc dans la page déjà chargée : le formulaire se remplit et se valide sans réseau, l'envoi passe par la boîte
 * d'envoi (`POST pair/cases`, `client_uuid` = clé d'idempotence). Code `VBG-nnnn` / `VDH-nnnn` attribué par le
 * serveur. Derniers dossiers du compte (liste sans notes). Réservé au compte actif `peer_space.access` (EnsurePeerSpaceAccess).
 */
final class CollectePageController extends Controller
{
    public function __invoke(Request $request, CollectOptions $options, ListCases $cases, ValidationCatalog $catalog): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $title = (string) __('peer.collecte.title');
        $recent = $cases->page($viewer, new CaseFilters, max(1, (int) config('xomiton.peer.recent_cases', 10)));

        return Inertia::render('PeerSpace/Collecte', [
            'title' => $title,
            'form' => $options->form(),
            'options' => $options->lists(),
            'rules' => $options->rules($request),
            'rules_version' => $catalog->lastUpdatedAt(),
            'today' => NotFutureDate::today()->format('Y-m-d'),
            'draft_days' => max(1, (int) config('xomiton.peer.collecte_draft_days', 1)),
            'recent' => CaseResource::collection($recent->items())->resolve($request),
        ])->withViewData(['title' => $title]);
    }
}
