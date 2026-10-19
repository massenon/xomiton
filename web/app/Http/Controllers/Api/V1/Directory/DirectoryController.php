<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Directory;

use App\Application\Alerts\RequestCallback;
use App\Application\Directory\BrowseDirectory;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Alerts\RequestCallbackRequest;
use App\Http\Requests\Directory\BrowseDirectoryRequest;
use App\Http\Resources\Alerts\AlertResource;
use App\Http\Resources\Directory\DirectoryContactResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

/**
 * Annuaire membre (P8-12, docs/API.md §19 ; prototype annuaire.php) : `GET directory` (fiches selon la portée du compte,
 * filtres `q`, `role`, `zone`, `commune`, pagination `page`, carte par département dans `meta.map` en page 1, null
 * ensuite) et `POST directory/{id}/callback` (« Demander à être rappelé·e » → RequestCallback, P5-02 : alerte besoin
 * `source = annuaire` confiée à ce contact sans RG-04, `idempotent:required,limit=directory_callback`). Réponses
 * `no-store, private` : la liste d'une portée réseau porte des numéros non publics.
 */
final class DirectoryController extends ApiController
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function index(BrowseDirectoryRequest $request, BrowseDirectory $directory): JsonResponse
    {
        $viewer = $request->viewer();
        $reach = BrowseDirectory::reachOf($viewer);

        if (! $reach->canBrowse()) {
            throw new AuthorizationException;
        }

        $filters = $request->filters();
        $number = $request->page();
        $page = $directory->page($viewer, $filters, (int) config('xomiton.directory.per_page', 24), $number);

        return $this->paginated($page, DirectoryContactResource::class, [
            'reach' => $reach->value,
            'filters' => $filters->toArray(),
            // Décompte de la carte en page 1 seulement : « Charger plus » n'en a pas besoin (requête GROUP BY évitée).
            'map' => $number === 1 ? $directory->map($viewer, $filters) : null,
        ])->withHeaders(self::PRIVATE_HEADERS);
    }

    public function callback(string $contact, RequestCallbackRequest $request, RequestCallback $callback): JsonResponse
    {
        $result = $callback($request->toData((int) $contact));
        $alert = $result->alert->loadMissing('routedContact');

        return $this->created(AlertResource::make($alert), route('api.signalements.show', ['code' => $alert->tracking_code]))
            ->withHeaders(self::PRIVATE_HEADERS);
    }
}
