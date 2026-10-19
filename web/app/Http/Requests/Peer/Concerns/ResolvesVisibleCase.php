<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer\Concerns;

use App\Application\Cases\Queries\ListCases;
use App\Models\CaseRecord;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dossier de l'adresse `pair/cases/{code}`, cherché **dans le scope** `CaseRecord::visibleTo` du compte : code
 * inconnu, illisible ou dossier d'un autre pair → même 404 neutre (aucun oracle sur les codes, énumérables). Résolu
 * avant l'autorisation (prepareForValidation), mémorisé pour le contrôleur.
 */
trait ResolvesVisibleCase
{
    private ?CaseRecord $visibleCase = null;

    public function caseRecord(): CaseRecord
    {
        if ($this->visibleCase !== null) {
            return $this->visibleCase;
        }

        $user = $this->user();
        $code = $this->route('code');
        $case = $user instanceof User && is_string($code) ? app(ListCases::class)->find($user, $code) : null;

        if ($case === null) {
            throw new NotFoundHttpException;
        }

        return $this->visibleCase = $case;
    }
}
