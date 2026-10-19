<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peer;

use App\Application\Cases\ValidationCatalog;
use App\Domain\Cases\CaseNotes;
use App\Domain\Cases\CaseReferral;
use App\Domain\Cases\FollowupStep;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\AgeRange;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Peer\ValidationRuleResource;
use Illuminate\Http\JsonResponse;

/**
 * Règles de validation actives (P9-02, docs/API.md §22) : catalogue activable de l'administration servi à la
 * collecte hors ligne (web P9-04, Flutter P15) pour appliquer côté client **les mêmes** contrôles que le serveur
 * (CaseRules). Lecture conditionnelle (`ETag`, 304 : groupe `conditional`) ; `meta.updated_at` = version du cache ;
 * `meta.options` = listes fermées de la collecte (tranches d'âge admises par les bornes si `age_range` est active,
 * toutes sinon ; orientations, étapes de suivi, longueur minimale des notes).
 */
final class ValidationRuleController extends ApiController
{
    public function __invoke(ValidationCatalog $catalog): JsonResponse
    {
        // Tranches proposées = tranches que le serveur accepte : filtrées par les bornes seulement si `age_range` est active.
        $ageRanges = $catalog->isActive(ValidationRuleKey::AgeRange)
            ? $catalog->plausibility()->acceptedAgeRanges()
            : AgeRange::cases();

        return $this->respond(ValidationRuleResource::collection($catalog->active()), meta: [
            'updated_at' => $catalog->lastUpdatedAt(),
            'options' => [
                'age_ranges' => array_map(static fn (AgeRange $range): array => [
                    'value' => $range->value,
                    'label' => $range->label(),
                ], $ageRanges),
                'referrals' => CaseReferral::OPTIONS,
                'followup_steps' => FollowupStep::OPTIONS,
                'notes_min_length' => CaseNotes::MIN_LENGTH,
            ],
        ]);
    }
}
