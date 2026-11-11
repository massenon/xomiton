<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peer;

use App\Application\Cases\CollectOptions;
use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Listes du formulaire de collecte (`GET pair/collect-options`, docs/API.md §22) : natures, départements et communes,
 * populations, sexes, types de violence (CollectOptions::form, ce que propose l'écran `/collecte`), puis tranches d'âge,
 * orientations, étapes et résultats de suivi (les mêmes listes que `validation-rules` `meta.options`). Référentiels
 * actifs seulement, aucune donnée personnelle ; lecture conditionnelle (`ETag`, 304 : groupe `conditional`). Accès :
 * compte pair actif (EnsurePeerSpaceAccess sur le groupe `pair/*`).
 */
final class CollectOptionsController extends ApiController
{
    public function __invoke(CollectOptions $options): JsonResponse
    {
        return $this->respond([
            ...$options->form(),
            ...$options->lists(),
            'followup_outcomes' => $options->outcomes(),
        ]);
    }
}
