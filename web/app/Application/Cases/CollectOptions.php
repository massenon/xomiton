<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Shared\Referentials;
use App\Domain\Cases\CaseNotes;
use App\Domain\Cases\CaseReferral;
use App\Domain\Cases\FollowupStep;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\AgeRange;
use App\Enums\CaseKind;
use App\Enums\FollowupOutcome;
use App\Enums\Sex;
use App\Http\Resources\Peer\ValidationRuleResource;
use Illuminate\Http\Request;

/**
 * Listes fermées et règles actives de la collecte (P9-04) : source unique de `GET pair/validation-rules`
 * (`meta.options`) et de l'écran `/collecte`, pour que le formulaire (hors ligne compris) propose exactement ce que le
 * serveur accepte. Tranches d'âge : celles des bornes de plausibilité si `age_range` est active, toutes sinon (Q25 a).
 */
final class CollectOptions
{
    public function __construct(
        private readonly ValidationCatalog $catalog,
        private readonly Referentials $referentials,
    ) {}

    /**
     * Listes de `meta.options` de l'API.
     *
     * @return array{age_ranges: list<array{value: string, label: string}>, referrals: list<string>, followup_steps: list<string>, notes_min_length: int}
     */
    public function lists(): array
    {
        $ageRanges = $this->catalog->isActive(ValidationRuleKey::AgeRange)
            ? $this->catalog->plausibility()->acceptedAgeRanges()
            : AgeRange::cases();

        return [
            'age_ranges' => array_map(static fn (AgeRange $range): array => [
                'value' => $range->value,
                'label' => $range->label(),
            ], $ageRanges),
            'referrals' => CaseReferral::OPTIONS,
            'followup_steps' => FollowupStep::OPTIONS,
            'notes_min_length' => CaseNotes::MIN_LENGTH,
        ];
    }

    /**
     * Règles actives du catalogue, sous la forme de l'API (`GET pair/validation-rules`).
     *
     * @return list<array<string, mixed>>
     */
    public function rules(Request $request): array
    {
        /** @var list<array<string, mixed>> $rules */
        $rules = ValidationRuleResource::collection($this->catalog->active())->resolve($request);

        return $rules;
    }

    /**
     * Données du formulaire de collecte : natures, départements et communes, populations, sexes, types de violence.
     *
     * @return array{kinds: list<array{value: string, label: string}>, zones: list<array{id: int, name: string, communes: list<array{id: int, name: string}>}>, populations: list<array{id: int, label: string}>, sexes: list<array{value: string, label: string}>, violence_types: list<array{id: int, label: string}>}
     */
    public function form(): array
    {
        return [
            'kinds' => array_map(static fn (CaseKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], CaseKind::cases()),
            'zones' => array_map(static fn (array $zone): array => [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'communes' => array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']),
            ], $this->referentials->zones()),
            'populations' => array_map(static fn (array $population): array => ['id' => $population['id'], 'label' => $population['label']], $this->referentials->populations()),
            'sexes' => array_map(static fn (Sex $sex): array => ['value' => $sex->value, 'label' => $sex->label()], Sex::cases()),
            'violence_types' => array_map(static fn (array $type): array => ['id' => $type['id'], 'label' => $type['label']], $this->referentials->violenceTypes()),
        ];
    }

    /**
     * Résultats d'une étape de suivi (liste fermée de la frise).
     *
     * @return list<array{value: string, label: string}>
     */
    public function outcomes(): array
    {
        return array_map(static fn (FollowupOutcome $outcome): array => ['value' => $outcome->value, 'label' => $outcome->label()], FollowupOutcome::cases());
    }
}
